import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'charte.dart';

/// Création du compte citoyen : identifiant, nom, prénoms, numéro de téléphone,
/// mot de passe. KUMA BANA vérifie auprès de l'état civil que l'identifiant
/// existe, que le nom et les prénoms sont les siens et que le numéro est bien
/// celui de la personne. Rien du registre n'est conservé.
class PageInscription extends StatefulWidget {
  const PageInscription({
    super.key,
    required this.onInscrit,
    required this.onRetour,
    required this.onConnexion,
  });
  final VoidCallback onInscrit;
  final VoidCallback onRetour;
  final VoidCallback onConnexion;

  @override
  State<PageInscription> createState() => _EtatInscription();
}

class _EtatInscription extends State<PageInscription> {
  final _identifiant = TextEditingController();
  final _nom = TextEditingController();
  final _prenoms = TextEditingController();
  final _telephone = TextEditingController();
  final _mdp = TextEditingController();
  final _mdp2 = TextEditingController();
  bool _voir = false;
  bool _envoi = false;
  String? _erreur;

  @override
  void dispose() {
    for (final c in [_identifiant, _nom, _prenoms, _telephone, _mdp, _mdp2]) {
      c.dispose();
    }
    super.dispose();
  }

  String? _verifier() {
    if (_identifiant.text.trim().isEmpty) {
      return 'Saisissez votre identifiant (par exemple BF-0004).';
    }
    if (_nom.text.trim().isEmpty) return 'Saisissez votre nom de famille.';
    if (_prenoms.text.trim().isEmpty) return 'Saisissez votre ou vos prénoms.';
    final chiffres = _telephone.text.replaceAll(RegExp(r'\D'), '');
    if (chiffres.length < 8) return 'Saisissez votre numéro de téléphone (8 chiffres).';
    if (_mdp.text.length < 8) return 'Le mot de passe doit faire au moins 8 caractères.';
    if (_mdp.text != _mdp2.text) return 'Les deux mots de passe ne sont pas identiques.';
    return null;
  }

  Future<void> _soumettre() async {
    final probleme = _verifier();
    if (probleme != null) {
      setState(() => _erreur = probleme);
      return;
    }
    setState(() {
      _erreur = null;
      _envoi = true;
    });
    try {
      await Api.instance.inscription(
        identifiant: _identifiant.text.trim().toUpperCase(),
        nom: _nom.text.trim(),
        prenoms: _prenoms.text.trim(),
        telephone: _telephone.text.trim(),
        motDePasse: _mdp.text,
      );
      if (mounted) widget.onInscrit();
    } on ErreurApi catch (e) {
      if (mounted) {
        setState(() {
          _erreur = e.message;
          _envoi = false;
        });
      }
    }
  }

  Widget _champ(String libelle, Widget champ, {String? aide}) => Padding(
    padding: const EdgeInsets.only(bottom: 18),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Text(
            libelle,
            style: const TextStyle(fontSize: 14.5, fontWeight: FontWeight.w600, color: Charte.texte),
          ),
        ),
        champ,
        if (aide != null)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              aide,
              style: const TextStyle(fontSize: 13, color: Charte.texteDoux, height: 1.35),
            ),
          ),
      ],
    ),
  );

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Scaffold(
        backgroundColor: Colors.white,
        body: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 8, 24, 32),
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
                  Center(
                    child: Image.asset('assets/kumabana.png', width: 170, semanticLabel: 'KUMA BANA'),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Créer mon compte',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700, color: Charte.marine),
                  ),
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Charte.fond,
                      borderRadius: BorderRadius.circular(Charte.rayonControle),
                    ),
                    child: const Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.verified_user_outlined, color: Charte.petrole, size: 22),
                        SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            'Votre identifiant, votre nom, vos prénoms et votre numéro sont vérifiés '
                            'auprès de l\'état civil : '
                            'personne ne peut ouvrir un compte à votre place.',
                            style: TextStyle(fontSize: 13.5, color: Charte.texte, height: 1.4),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 22),
                  if (_erreur != null)
                    Semantics(
                      liveRegion: true,
                      child: Container(
                        margin: const EdgeInsets.only(bottom: 16),
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFE4E6),
                          borderRadius: BorderRadius.circular(Charte.rayonControle),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(Icons.error, color: Color(0xFFB00E1B), size: 20),
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
                    'Identifiant unique',
                    TextField(
                      controller: _identifiant,
                      textCapitalization: TextCapitalization.characters,
                      autofillHints: const [AutofillHints.username],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        hintText: 'Par exemple BF-0004',
                        prefixIcon: Icon(Icons.badge_outlined),
                      ),
                    ),
                    aide: 'Le numéro qui figure sur votre pièce d\'identité.',
                  ),
                  _champ(
                    'Nom',
                    TextField(
                      controller: _nom,
                      textCapitalization: TextCapitalization.characters,
                      autofillHints: const [AutofillHints.familyName],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        hintText: 'Par exemple KABORE',
                        prefixIcon: Icon(Icons.person_outline),
                      ),
                    ),
                  ),
                  _champ(
                    'Prénoms',
                    TextField(
                      controller: _prenoms,
                      textCapitalization: TextCapitalization.words,
                      autofillHints: const [AutofillHints.givenName],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        hintText: 'Par exemple Issouf',
                        prefixIcon: Icon(Icons.person_outline),
                      ),
                    ),
                    aide: 'Tous vos prénoms, comme sur votre acte de naissance.',
                  ),
                  _champ(
                    'Numéro de téléphone',
                    TextField(
                      controller: _telephone,
                      keyboardType: TextInputType.phone,
                      autofillHints: const [AutofillHints.telephoneNumber],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(
                        hintText: '70 00 00 04',
                        prefixIcon: Icon(Icons.phone_outlined),
                        prefixText: '+226 ',
                      ),
                    ),
                    aide: 'Celui que vous avez déclaré à l\'état civil.',
                  ),
                  _champ(
                    'Mot de passe',
                    TextField(
                      controller: _mdp,
                      obscureText: !_voir,
                      autofillHints: const [AutofillHints.newPassword],
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        hintText: 'Au moins 8 caractères',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          tooltip: _voir ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
                          icon: Icon(_voir ? Icons.visibility_off : Icons.visibility),
                          onPressed: () => setState(() => _voir = !_voir),
                        ),
                      ),
                    ),
                  ),
                  _champ(
                    'Confirmer le mot de passe',
                    TextField(
                      controller: _mdp2,
                      obscureText: !_voir,
                      onSubmitted: (_) => _soumettre(),
                      decoration: const InputDecoration(
                        hintText: 'Le même mot de passe',
                        prefixIcon: Icon(Icons.lock_outline),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  FilledButton(
                    style: FilledButton.styleFrom(backgroundColor: Charte.bleu),
                    onPressed: _envoi ? null : _soumettre,
                    child: _envoi
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.white),
                          )
                        : const Text('Créer mon compte'),
                  ),
                  const SizedBox(height: 12),
                  TextButton(
                    onPressed: widget.onConnexion,
                    child: const Text('J\'ai déjà un compte : me connecter'),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Démonstration : BF-0004, KABORE Issouf, téléphone 70 00 00 04.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 12.5, color: Charte.texteDoux),
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
