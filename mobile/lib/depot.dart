import 'package:flutter/foundation.dart';

import 'api.dart';

/// Données de l'espace citoyen, partagées par tous les écrans : une seule
/// source, un seul rafraîchissement, des compteurs toujours cohérents.
class Depot extends ChangeNotifier {
  Depot._();
  static final Depot instance = Depot._();

  List<Map<String, dynamic>> aValider = [];
  List<Map<String, dynamic>> historique = [];
  List<Map<String, dynamic>> accords = [];
  List<Map<String, dynamic>> preuves = [];
  List<Map<String, dynamic>> messages = [];

  bool charge = false;
  bool enChargement = false;
  String? erreur;

  Future<void> charger() async {
    enChargement = true;
    erreur = null;
    notifyListeners();
    try {
      final r = await Future.wait([
        Api.instance.liste('/citoyen/demandes-a-valider'),
        Api.instance.liste('/citoyen/historique'),
        Api.instance.liste('/citoyen/consentements'),
        Api.instance.liste('/citoyen/preuves'),
        Api.instance.liste('/citoyen/messages'),
      ]);
      aValider = r[0];
      historique = r[1];
      accords = r[2];
      preuves = r[3];
      messages = r[4];
      charge = true;
    } on ErreurApi catch (e) {
      erreur = e.message;
    }
    enChargement = false;
    notifyListeners();
  }

  /// accepter, refuser ou retirer : l'API décide, on recharge ensuite.
  Future<void> decider(Object id, String action) async {
    await Api.instance.envoyer('/citoyen/consentements/$id/$action');
    await charger();
  }

  int get preuvesValides => preuves.where((p) => p['revoquee'] != true).length;
  int get accordsActifs =>
      accords.where((a) => a['statut'] == 'accepte').length;

  void vider() {
    aValider = [];
    historique = [];
    accords = [];
    preuves = [];
    messages = [];
    charge = false;
    erreur = null;
  }
}
