import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'charte.dart';

const _motDePasseDemo = 'kumabana-demo';

const _comptesDemo = [
  ('Ousmane (BF-0001)', 'BF-0001'),
  ('Aminata (BF-0003)', 'BF-0003'),
  ('Fatimata (BF-0005)', 'BF-0005'),
];

/// Connexion : une page blanche, un logo, deux champs, un bouton.
class PageConnexion extends StatefulWidget {
  const PageConnexion({
    super.key,
    required this.onConnecte,
    required this.onRetour,
    this.onInscription,
  });
  final VoidCallback onConnecte;
  final VoidCallback onRetour;
  final VoidCallback? onInscription;

  @override
  State<PageConnexion> createState() => _EtatConnexion();
}

class _EtatConnexion extends State<PageConnexion> {
  final _email = TextEditingController();
  final _mdp = TextEditingController();
  bool _voir = false;
  bool _envoi = false;
  String? _erreur;

  @override
  void dispose() {
    _email.dispose();
    _mdp.dispose();
    super.dispose();
  }

  Future<void> _soumettre() async {
    if (_email.text.trim().isEmpty || _mdp.text.isEmpty) {
      setState(
        () => _erreur = 'Saisissez votre identifiant et votre mot de passe.',
      );
      return;
    }
    setState(() {
      _erreur = null;
      _envoi = true;
    });
    try {
      final login = _email.text.trim();
      await Api.instance.connexion(
        login.contains('@') ? login : login.toUpperCase(),
        _mdp.text,
      );
      if (mounted) widget.onConnecte();
    } on ErreurApi catch (e) {
      if (mounted) {
        setState(() {
          _erreur = e.message;
          _envoi = false;
        });
      }
    }
  }

  Widget _champ(String libelle, Widget champ) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(
          libelle,
          style: const TextStyle(
            fontSize: 14.5,
            fontWeight: FontWeight.w600,
            color: Charte.texte,
          ),
        ),
      ),
      champ,
    ],
  );

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
            child: AutofillGroup(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Align(
                    alignment: Alignment.centerLeft,
                    child: IconButton(
                      tooltip: 'Retour',
                      onPressed: widget.onRetour,
                      icon: const Icon(Icons.arrow_back_rounded),
                      color: Charte.marine,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Center(
                    child: Image.asset(
                      'assets/kumabana.png',
                      width: 200,
                      semanticLabel: 'KUMA BANA',
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Connectez-vous',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 22,
                      fontWeight: FontWeight.w700,
                      color: Charte.marine,
                    ),
                  ),
                  const SizedBox(height: 24),
                  if (_erreur != null)
                    Semantics(
                      liveRegion: true,
                      child: Container(
                        margin: const EdgeInsets.only(bottom: 16),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFE4E6),
                          borderRadius: BorderRadius.circular(
                            Charte.rayonControle,
                          ),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(
                              Icons.error,
                              color: Color(0xFFB00E1B),
                              size: 20,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _erreur!,
                                style: const TextStyle(
                                  color: Color(0xFFB00E1B),
                                  fontWeight: FontWeight.w600,
                                  height: 1.35,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  _champ(
                    'Identifiant',
                    TextField(
                      controller: _email,
                      textCapitalization: TextCapitalization.characters,
                      autofillHints: const [AutofillHints.username],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        hintText: 'Par exemple BF-0001',
                        prefixIcon: Icon(Icons.badge_outlined),
                      ),
                    ),
                  ),
                  const SizedBox(height: 18),
                  _champ(
                    'Mot de passe',
                    TextField(
                      controller: _mdp,
                      obscureText: !_voir,
                      autofillHints: const [AutofillHints.password],
                      onSubmitted: (_) => _soumettre(),
                      decoration: InputDecoration(
                        hintText: 'Entrez votre mot de passe',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          tooltip: _voir
                              ? 'Masquer le mot de passe'
                              : 'Afficher le mot de passe',
                          icon: Icon(
                            _voir ? Icons.visibility_off : Icons.visibility,
                          ),
                          onPressed: () => setState(() => _voir = !_voir),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 26),
                  FilledButton(
                    style: FilledButton.styleFrom(backgroundColor: Charte.bleu),
                    onPressed: _envoi ? null : _soumettre,
                    child: _envoi
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(
                              strokeWidth: 2.5,
                              color: Colors.white,
                            ),
                          )
                        : const Text('Se connecter'),
                  ),
                  if (widget.onInscription != null) ...[
                    const SizedBox(height: 12),
                    TextButton(
                      onPressed: widget.onInscription,
                      child: const Text('Pas encore de compte ? Créer mon compte'),
                    ),
                  ],
                  const SizedBox(height: 28),
                  const Text(
                    'Comptes de démonstration (fictifs)',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w600,
                      color: Charte.texteDoux,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Mot de passe : $_motDePasseDemo',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 13, color: Charte.texteDoux),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    alignment: WrapAlignment.center,
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final c in _comptesDemo)
                        ActionChip(
                          avatar: const Icon(
                            Icons.person,
                            size: 18,
                            color: Charte.petrole,
                          ),
                          label: Text(c.$1),
                          backgroundColor: const Color(0xFFEAF6FC),
                          side: BorderSide.none,
                          onPressed: () => setState(() {
                            _email.text = c.$2;
                            _mdp.text = _motDePasseDemo;
                            _erreur = null;
                          }),
                        ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
