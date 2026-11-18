import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

const String _adresseConfiguree = String.fromEnvironment('API_URL');

/// Adresse de l'API, modifiable avec --dart-define=API_URL=...
/// Par défaut : l'émulateur Android joint l'ordinateur via 10.0.2.2, les autres via 127.0.0.1.
String get adresseApi {
  if (_adresseConfiguree.isNotEmpty) return _adresseConfiguree;
  if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
    return 'http://10.0.2.2:8000/api';
  }
  return 'http://127.0.0.1:8000/api';
}

/// Adresse du serveur sans « /api » : le portail y relaie aussi les notifications.
String get adresseServeur {
  final a = adresseApi.endsWith('/') ? adresseApi.substring(0, adresseApi.length - 1) : adresseApi;
  return a.endsWith('/api') ? a.substring(0, a.length - 4) : a;
}

class ErreurApi implements Exception {
  ErreurApi(this.message);
  final String message;
  @override
  String toString() => message;
}

class Utilisateur {
  Utilisateur(this.nom, this.role, this.email);
  final String nom;
  final String role;
  final String email;
}

/// Client de l'API : jeton mémorisé sur l'appareil, messages d'erreur en français.
class Api {
  Api._();
  static final Api instance = Api._();

  static const _cleJeton = 'kumabana.jeton';
  String? _jeton;
  Utilisateur? utilisateur;

  Map<String, String> get _entetes => {
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    if (_jeton != null) 'Authorization': 'Bearer $_jeton',
  };

  Future<dynamic> _appeler(
    String methode,
    String chemin, {
    Object? corps,
  }) async {
    final uri = Uri.parse('$adresseApi$chemin');
    http.Response r;
    try {
      final req = http.Request(methode, uri)..headers.addAll(_entetes);
      if (corps != null) req.body = jsonEncode(corps);
      r = await http.Response.fromStream(await req.send())
          .timeout(const Duration(seconds: 15));
    } catch (_) {
      throw ErreurApi(
        'Impossible de joindre le serveur. Vérifiez votre connexion.',
      );
    }
    dynamic json;
    try {
      json = r.body.isEmpty ? null : jsonDecode(r.body);
    } catch (_) {
      json = null;
    }
    if (r.statusCode >= 200 && r.statusCode < 300) return json;
    if (r.statusCode == 401 && _jeton != null) {
      await deconnexionLocale();
      throw ErreurApi('Votre session a expiré. Reconnectez-vous.');
    }
    if (json is Map && json['message'] is String) {
      throw ErreurApi(json['message'] as String);
    }
    throw ErreurApi('Une erreur est survenue (code ${r.statusCode}).');
  }

  Future<dynamic> lire(String chemin) => _appeler('GET', chemin);
  Future<dynamic> envoyer(String chemin, [Object? corps]) =>
      _appeler('POST', chemin, corps: corps ?? {});

  Utilisateur _lireUtilisateur(Map<String, dynamic> j) => Utilisateur(
    j['nom'] as String? ?? '',
    j['role'] as String? ?? '',
    j['email'] as String? ?? j['identifiant'] as String? ?? '',
  );

  /// Reprend la session mémorisée, sans erreur si elle n'existe plus.
  Future<bool> reprendre() async {
    try {
      final p = await SharedPreferences.getInstance();
      _jeton = p.getString(_cleJeton);
      if (_jeton == null) return false;
      final j = await _appeler('GET', '/auth/moi') as Map<String, dynamic>;
      final u = _lireUtilisateur(j);
      if (u.role != 'citoyen') {
        await deconnexionLocale();
        return false;
      }
      utilisateur = u;
      return true;
    } catch (_) {
      _jeton = null;
      return false;
    }
  }

  /// [login] : l'identifiant du citoyen (BF-0001) ou, à défaut, une adresse e-mail.
  Future<void> connexion(String login, String motDePasse) async {
    final cle = login.contains('@') ? 'email' : 'identifiant';
    final j = await _appeler(
      'POST',
      '/auth/connexion',
      corps: {cle: login, 'mot_de_passe': motDePasse},
    ) as Map<String, dynamic>;
    final u = _lireUtilisateur(j['utilisateur'] as Map<String, dynamic>);
    if (u.role != 'citoyen') {
      throw ErreurApi(
        "Cette application est réservée aux citoyens. Utilisez le portail web pour votre rôle.",
      );
    }
    _jeton = j['jeton'] as String;
    utilisateur = u;
    final p = await SharedPreferences.getInstance();
    await p.setString(_cleJeton, _jeton!);
  }

  /// Crée le compte du citoyen. L'identité est vérifiée par le serveur auprès de
  /// l'état civil ; en cas de succès, le citoyen est connecté directement.
  Future<void> inscription({
    required String identifiant,
    required String nom,
    required String prenoms,
    required String telephone,
    required String motDePasse,
  }) async {
    final j = await _appeler(
      'POST',
      '/auth/inscription',
      corps: {
        'identifiant': identifiant,
        'nom': nom,
        'prenoms': prenoms,
        'telephone': telephone,
        'mot_de_passe': motDePasse,
      },
    ) as Map<String, dynamic>;
    _jeton = j['jeton'] as String;
    utilisateur = _lireUtilisateur(j['utilisateur'] as Map<String, dynamic>);
    final p = await SharedPreferences.getInstance();
    await p.setString(_cleJeton, _jeton!);
  }

  Future<void> deconnexion() async {
    try {
      await _appeler('POST', '/auth/deconnexion', corps: {});
    } catch (_) {}
    await deconnexionLocale();
  }

  Future<void> deconnexionLocale() async {
    _jeton = null;
    utilisateur = null;
    try {
      final p = await SharedPreferences.getInstance();
      await p.remove(_cleJeton);
    } catch (_) {}
  }

  Future<List<Map<String, dynamic>>> liste(String chemin) async {
    final j = await lire(chemin) as Map<String, dynamic>;
    return (j['donnees'] as List).cast<Map<String, dynamic>>();
  }
}

const _mois = [
  'janvier',
  'février',
  'mars',
  'avril',
  'mai',
  'juin',
  'juillet',
  'août',
  'septembre',
  'octobre',
  'novembre',
  'décembre',
];

/// « 12 mars 2026 à 14:05 », en heure locale.
String dateFr(String? iso, {bool heure = true}) {
  if (iso == null) return '';
  final d = DateTime.tryParse(iso)?.toLocal();
  if (d == null) return iso;
  final base = '${d.day} ${_mois[d.month - 1]} ${d.year}';
  if (!heure) return base;
  return '$base à ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
}
