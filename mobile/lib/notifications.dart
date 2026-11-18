import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:http/http.dart' as http;

import 'api.dart';

/// Notifications push, sans Google : l'application écoute le sujet ntfy secret du
/// citoyen, sur le serveur ntfy auto-hébergé de KUMA BANA (relayé en lecture seule
/// par le portail).
///
/// Sur Android, garder une connexion ouverte quand l'application est fermée exige
/// un « service de premier plan » : une petite notification discrète et permanente
/// indique que KUMA BANA reste à l'écoute.
///
/// Le message reçu ne contient aucune donnée : il invite seulement à ouvrir
/// l'application, qui va chercher le détail par l'API authentifiée.
class Notifications {
  Notifications._();

  static const _cleUrl = 'push_url';
  static const _cleDepuis = 'push_depuis';

  static bool get _android =>
      !kIsWeb && defaultTargetPlatform == TargetPlatform.android;

  /// À appeler une fois au démarrage, avant runApp.
  static void preparer() {
    if (_android) FlutterForegroundTask.initCommunicationPort();
  }

  /// Après la connexion : demande l'autorisation, récupère le sujet secret et
  /// lance l'écoute. Ne lève jamais d'erreur : sans push, l'appli fonctionne.
  static Future<void> demarrer() async {
    if (!_android) return;
    try {
      final abonnement = await Api.instance.lire('/citoyen/notifications/abonnement')
          as Map<String, dynamic>;
      if (abonnement['actif'] != true || abonnement['chemin'] is! String) {
        await arreter();
        return;
      }

      if (await FlutterForegroundTask.checkNotificationPermission() !=
          NotificationPermission.granted) {
        await FlutterForegroundTask.requestNotificationPermission();
      }

      final url = '$adresseServeur${abonnement['chemin']}';
      final precedente = await FlutterForegroundTask.getData<String>(key: _cleUrl);
      if (precedente != url) {
        // Nouveau compte ou nouveau serveur : on repart de « maintenant ».
        await FlutterForegroundTask.removeData(key: _cleDepuis);
      }
      await FlutterForegroundTask.saveData(key: _cleUrl, value: url);

      FlutterForegroundTask.init(
        androidNotificationOptions: AndroidNotificationOptions(
          channelId: 'kumabana_ecoute',
          channelName: 'Écoute des demandes',
          channelDescription:
              'Permet de recevoir les demandes d\'accord, même application fermée.',
          channelImportance: NotificationChannelImportance.MIN,
          priority: NotificationPriority.MIN,
          onlyAlertOnce: true,
        ),
        iosNotificationOptions: const IOSNotificationOptions(
          showNotification: false,
          playSound: false,
        ),
        foregroundTaskOptions: ForegroundTaskOptions(
          eventAction: ForegroundTaskEventAction.nothing(),
          autoRunOnBoot: true,
          autoRunOnMyPackageReplaced: true,
          allowWakeLock: true,
          allowWifiLock: true,
        ),
      );

      if (await FlutterForegroundTask.isRunningService) {
        await FlutterForegroundTask.restartService();
      } else {
        await FlutterForegroundTask.startService(
          serviceId: 4242,
          serviceTypes: [ForegroundServiceTypes.remoteMessaging],
          notificationTitle: 'KUMA BANA',
          notificationText: 'À l\'écoute des demandes qui vous concernent',
          callback: demarrerEcoute,
        );
      }
    } catch (e) {
      debugPrint('Notifications indisponibles : $e');
    }
  }

  /// À la déconnexion : plus aucune notification pour ce compte sur l'appareil.
  static Future<void> arreter() async {
    if (!_android) return;
    try {
      await FlutterForegroundTask.stopService();
      await FlutterForegroundTask.removeData(key: _cleUrl);
      await FlutterForegroundTask.removeData(key: _cleDepuis);
    } catch (_) {}
  }

  /// Prévenu par le service à chaque message (appli ouverte) : on recharge.
  static void ecouterDansAppli(void Function() surMessage) {
    if (!_android) return;
    final ancien = _rappel;
    if (ancien != null) FlutterForegroundTask.removeTaskDataCallback(ancien);
    _rappel = (Object data) {
      if (data is Map && data['push'] == true) surMessage();
    };
    FlutterForegroundTask.addTaskDataCallback(_rappel!);
  }

  static DataCallback? _rappel;
}

/// Point d'entrée du service (isolate séparé) : doit rester une fonction de haut niveau.
@pragma('vm:entry-point')
void demarrerEcoute() {
  FlutterForegroundTask.setTaskHandler(EcouteNtfy());
}

/// Écoute le flux JSON de ntfy (une ligne par évènement) et affiche une
/// notification à chaque message. Se reconnecte seule après une coupure, en
/// reprenant après le dernier message reçu pour n'en perdre aucun.
class EcouteNtfy extends TaskHandler {
  final _notifs = FlutterLocalNotificationsPlugin();
  http.Client? _client;
  bool _actif = true;
  int _prochainId = 1;

  static const _canal = AndroidNotificationDetails(
    'kumabana_demandes',
    'Demandes et consultations',
    channelDescription:
        'Une administration attend votre accord, ou a consulté une information vous concernant.',
    importance: Importance.high,
    priority: Priority.high,
    icon: '@mipmap/ic_launcher',
  );

  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {
    await _notifs.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
    );
    unawaited(_boucle());
  }

  Future<void> _boucle() async {
    var attente = const Duration(seconds: 3);
    while (_actif) {
      try {
        await _ecouter();
        attente = const Duration(seconds: 3);
      } catch (_) {
        // Serveur injoignable : on réessaie, de plus en plus espacé (max 1 min).
      }
      if (!_actif) break;
      await Future<void>.delayed(attente);
      attente = Duration(seconds: (attente.inSeconds * 2).clamp(3, 60));
    }
  }

  Future<void> _ecouter() async {
    final url = await FlutterForegroundTask.getData<String>(key: Notifications._cleUrl);
    if (url == null) {
      await Future<void>.delayed(const Duration(seconds: 30));
      return;
    }
    final depuis = await FlutterForegroundTask.getData<String>(key: Notifications._cleDepuis);
    final uri = Uri.parse(url).replace(
      queryParameters: depuis == null ? null : {'since': depuis},
    );

    _client?.close();
    final client = _client = http.Client();
    final reponse = await client.send(http.Request('GET', uri));
    if (reponse.statusCode != 200) {
      throw http.ClientException('ntfy ${reponse.statusCode}', uri);
    }

    await for (final ligne in reponse.stream
        .transform(utf8.decoder)
        .transform(const LineSplitter())
        // ntfy envoie un signal de vie toutes les 45 s : sans rien pendant 2 min, on reconnecte.
        .timeout(const Duration(minutes: 2))) {
      if (ligne.trim().isEmpty) continue;
      final evt = jsonDecode(ligne);
      if (evt is! Map || evt['event'] != 'message') continue;

      await FlutterForegroundTask.saveData(
        key: Notifications._cleDepuis,
        value: evt['id'] as String,
      );
      await _notifs.show(
        id: _prochainId++,
        title: evt['title'] as String? ?? 'KUMA BANA',
        body: evt['message'] as String? ?? 'Ouvrez l\'application.',
        notificationDetails: const NotificationDetails(android: _canal),
      );
      FlutterForegroundTask.sendDataToMain({'push': true});
    }
  }

  @override
  void onRepeatEvent(DateTime timestamp) {}

  @override
  Future<void> onDestroy(DateTime timestamp, bool isTimeout) async {
    _actif = false;
    _client?.close();
  }

  @override
  void onNotificationPressed() => FlutterForegroundTask.launchApp();
}
