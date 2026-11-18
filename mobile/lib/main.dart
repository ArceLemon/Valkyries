import 'package:material_ui/material_ui.dart';

import 'package:flutter/services.dart';

import 'api.dart';
import 'charte.dart';
import 'bienvenue.dart';
import 'connexion.dart';
import 'depot.dart';
import 'ecrans.dart';
import 'inscription.dart';
import 'notifications.dart';
import 'splash.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  Notifications.preparer();
  runApp(const KumaBanaApp());
}

class KumaBanaApp extends StatelessWidget {
  const KumaBanaApp({super.key, this.demarrage});

  /// Remplaçable dans les tests : décide si une session existe.
  final Future<bool> Function()? demarrage;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'KUMA BANA',
      debugShowCheckedModeBanner: false,
      theme: Charte.theme(),
      home: Racine(demarrage: demarrage ?? Api.instance.reprendre),
    );
  }
}

enum _Phase { splash, bienvenue, connexion, inscription, espace }

/// Splash au moins 2 secondes, puis connexion ou espace citoyen.
class Racine extends StatefulWidget {
  const Racine({
    super.key,
    required this.demarrage,
    this.dureeMinimale = const Duration(milliseconds: 2400),
  });
  final Future<bool> Function() demarrage;
  final Duration dureeMinimale;

  @override
  State<Racine> createState() => _EtatRacine();
}

class _EtatRacine extends State<Racine> {
  _Phase _phase = _Phase.splash;

  @override
  void initState() {
    super.initState();
    _demarrer();
  }

  Future<void> _demarrer() async {
    final minimum = Future<void>.delayed(widget.dureeMinimale);
    final connecte = await widget.demarrage();
    await minimum;
    if (!mounted) return;
    setState(() => _phase = connecte ? _Phase.espace : _Phase.bienvenue);
  }

  @override
  Widget build(BuildContext context) {
    final ecran = switch (_phase) {
      _Phase.splash => const EcranSplash(key: ValueKey('splash')),
      _Phase.bienvenue => PageBienvenue(
        key: const ValueKey('bienvenue'),
        onConnexion: () => setState(() => _phase = _Phase.connexion),
        onInscription: () => setState(() => _phase = _Phase.inscription),
      ),
      _Phase.connexion => PageConnexion(
        key: const ValueKey('connexion'),
        onConnecte: () => setState(() => _phase = _Phase.espace),
        onRetour: () => setState(() => _phase = _Phase.bienvenue),
        onInscription: () => setState(() => _phase = _Phase.inscription),
      ),
      _Phase.inscription => PageInscription(
        key: const ValueKey('inscription'),
        onInscrit: () => setState(() => _phase = _Phase.espace),
        onRetour: () => setState(() => _phase = _Phase.bienvenue),
        onConnexion: () => setState(() => _phase = _Phase.connexion),
      ),
      _Phase.espace => EspaceCitoyen(
        key: const ValueKey('espace'),
        onDeconnecte: () => setState(() => _phase = _Phase.bienvenue),
      ),
    };
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 500),
      child: ecran,
    );
  }
}

class EspaceCitoyen extends StatefulWidget {
  const EspaceCitoyen({super.key, required this.onDeconnecte});
  final VoidCallback onDeconnecte;

  @override
  State<EspaceCitoyen> createState() => _EtatEspace();
}

class _EtatEspace extends State<EspaceCitoyen> {
  int _onglet = 0;

  @override
  void initState() {
    super.initState();
    Depot.instance.charger();
    // Notification reçue appli ouverte : les écrans se mettent à jour d'eux-mêmes.
    Notifications.ecouterDansAppli(Depot.instance.charger);
    Notifications.demarrer();
  }

  Future<void> _quitter() async {
    await Notifications.arreter();
    await Api.instance.deconnexion();
    Depot.instance.vider();
    widget.onDeconnecte();
  }

  void _aller(int i) {
    HapticFeedback.selectionClick();
    setState(() => _onglet = i);
  }

  @override
  Widget build(BuildContext context) {
    final pages = <Widget>[
      EcranAccueil(aller: _aller, onQuitter: _quitter),
      const EcranDemandes(),
      const EcranHistorique(),
      const EcranPreuves(),
    ];
    return Scaffold(
      body: IndexedStack(index: _onglet, children: pages),
      bottomNavigationBar: ListenableBuilder(
        listenable: Depot.instance,
        builder: (context, _) {
          final n = Depot.instance.aValider.length;
          return DecoratedBox(
            decoration: BoxDecoration(
              boxShadow: [
                BoxShadow(
                  color: Charte.petrole.withValues(alpha: 0.12),
                  blurRadius: 24,
                  offset: const Offset(0, -6),
                ),
              ],
            ),
            child: NavigationBar(
              selectedIndex: _onglet,
              onDestinationSelected: _aller,
              destinations: [
                const NavigationDestination(
                  icon: Icon(Icons.home_outlined),
                  selectedIcon: Icon(Icons.home_rounded),
                  label: 'Accueil',
                ),
                NavigationDestination(
                  icon: Badge(
                    isLabelVisible: n > 0,
                    label: Text('$n'),
                    backgroundColor: Charte.rouge,
                    child: const Icon(Icons.handshake_outlined),
                  ),
                  selectedIcon: Badge(
                    isLabelVisible: n > 0,
                    label: Text('$n'),
                    backgroundColor: Charte.rouge,
                    child: const Icon(Icons.handshake_rounded),
                  ),
                  label: 'Accords',
                ),
                const NavigationDestination(
                  icon: Icon(Icons.visibility_outlined),
                  selectedIcon: Icon(Icons.visibility_rounded),
                  label: 'Historique',
                ),
                const NavigationDestination(
                  icon: Icon(Icons.qr_code_2_rounded),
                  selectedIcon: Icon(Icons.qr_code_2_rounded),
                  label: 'Preuves',
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
