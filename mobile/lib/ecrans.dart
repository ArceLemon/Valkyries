import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';
import 'package:qr_flutter/qr_flutter.dart';

import 'api.dart';
import 'charte.dart';
import 'depot.dart';
import 'validation.dart';
import 'widgets.dart';

/// Contenu d'un écran selon l'état des données : erreur, chargement ou contenu.
Widget _selonEtat(Depot d, List<Widget> Function() contenu) {
  if (!d.charge && d.erreur != null) {
    return ErreurReseau(message: d.erreur!, onRetry: d.charger) as Widget;
  }
  if (!d.charge) return const Squelettes();
  return Column(children: contenu());
}

const _typesHistorique = {
  'donnee.consultee': (
    'A consulté une donnée',
    Icons.visibility_rounded,
    Charte.bleu,
  ),
  'consentement.accepte': (
    'Vous avez accepté',
    Icons.check_circle_rounded,
    Charte.vert,
  ),
  'consentement.refuse': (
    'Vous avez refusé',
    Icons.cancel_rounded,
    Charte.rouge,
  ),
  'consentement.retire': (
    'Vous avez retiré votre accord',
    Icons.undo_rounded,
    Color(0xFFC99700),
  ),
};

// ------------------------------------------------------------------ Accueil

class EcranAccueil extends StatelessWidget {
  const EcranAccueil({super.key, required this.aller, required this.onQuitter});
  final void Function(int) aller;
  final VoidCallback onQuitter;

  @override
  Widget build(BuildContext context) {
    final depot = Depot.instance;
    final nom = Api.instance.utilisateur?.nom ?? '';
    final prenom = nom.split(' ').first;
    return ListenableBuilder(
      listenable: depot,
      builder: (context, _) {
        final n = depot.aValider.length;
        final consultations = depot.historique
            .where((h) => h['type'] == 'donnee.consultee')
            .length;
        return PageDefilante(
          titre: 'Bonjour${prenom.isEmpty ? '' : ' $prenom'}',
          avant: Avatar(nom.isEmpty ? 'Citoyen' : nom, taille: 46),
          onRefresh: depot.charger,
          actions: [
            BoutonEntete(
              icone: Icons.logout_rounded,
              libelle: 'Se déconnecter',
              onTap: onQuitter,
            ),
          ],
          enfants: [
            const SizedBox(height: 8),
            Row(
              children: [
                _Raccourci(
                  icone: Icons.handshake_rounded,
                  libelle: 'Accords',
                  nombre: n,
                  alerte: n > 0,
                  onTap: () => aller(1),
                ),
                _Raccourci(
                  icone: Icons.visibility_rounded,
                  libelle: 'Historique',
                  nombre: consultations,
                  onTap: () => aller(2),
                ),
                _Raccourci(
                  icone: Icons.qr_code_2_rounded,
                  libelle: 'Preuves',
                  nombre: depot.preuvesValides,
                  onTap: () => aller(3),
                ),
                _Raccourci(
                  icone: Icons.sms_rounded,
                  libelle: 'Messages',
                  nombre: depot.messages.length,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const EcranMessages(),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 22),
            _selonEtat(
              depot,
              () => [
                Apparition(
                  child: _Etat(n: n, onTap: () => aller(1)),
                ),
                TitreSection(
                  'Dernières consultations',
                  action: depot.historique.isEmpty ? null : 'Tout voir',
                  onAction: () => aller(2),
                ),
                if (depot.historique.isEmpty)
                  const EtatVide(
                    icone: Icons.shield_rounded,
                    titre: 'Rien à signaler',
                    texte: 'Personne n’a consulté vos données pour le moment.',
                    couleur: Charte.bleu,
                  )
                else
                  for (var i = 0; i < depot.historique.take(4).length; i++)
                    _LigneRecente(
                      depot.historique[i],
                      derniere: i == depot.historique.take(4).length - 1,
                    ),
              ],
            ),
          ],
        );
      },
    );
  }
}

/// Un raccourci : une icône dans un rond, son nom dessous, un compteur discret.
class _Raccourci extends StatelessWidget {
  const _Raccourci({
    required this.icone,
    required this.libelle,
    required this.nombre,
    required this.onTap,
    this.alerte = false,
  });
  final IconData icone;
  final String libelle;
  final int nombre;
  final bool alerte;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Semantics(
        button: true,
        label: '$libelle : $nombre',
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: onTap,
          child: ExcludeSemantics(
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 6),
              child: Column(
                children: [
                  Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Container(
                        width: 64,
                        height: 64,
                        decoration: const BoxDecoration(
                          color: Color(0xFFDAF0FA),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(icone, color: Charte.bleu, size: 30),
                      ),
                      Positioned(
                        right: -6,
                        top: -4,
                        child: Container(
                          constraints: const BoxConstraints(minWidth: 24),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 7,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: alerte ? Charte.rouge : Charte.marine,
                            borderRadius: BorderRadius.circular(99),
                            border: Border.all(color: Charte.fond, width: 2),
                          ),
                          child: Text(
                            '$nombre',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    libelle,
                    style: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w600,
                      color: Charte.texte,
                    ),
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

/// Une ligne qui dit où on en est : une action à faire, ou rien.
class _Etat extends StatelessWidget {
  const _Etat({required this.n, required this.onTap});
  final int n;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final action = n > 0;
    return InkWell(
      borderRadius: BorderRadius.circular(14),
      onTap: action ? onTap : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 2),
        child: Row(
          children: [
            Icon(
              action ? Icons.notifications_rounded : Icons.check_circle_rounded,
              color: action ? Charte.rouge : Charte.vert,
              size: 24,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                action
                    ? (n == 1
                          ? '1 demande attend votre accord'
                          : '$n demandes attendent votre accord')
                    : 'Tout est à jour',
                style: const TextStyle(
                  fontSize: 15.5,
                  fontWeight: FontWeight.w600,
                  color: Charte.texte,
                ),
              ),
            ),
            if (action)
              const Icon(Icons.chevron_right_rounded, color: Charte.texteDoux),
          ],
        ),
      ),
    );
  }
}

class _LigneRecente extends StatelessWidget {
  const _LigneRecente(this.h, {required this.derniere});
  final Map<String, dynamic> h;
  final bool derniere;

  @override
  Widget build(BuildContext context) {
    final t =
        _typesHistorique[h['type']] ??
        ('Événement', Icons.info_rounded, Charte.bleu);
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        border: derniere
            ? null
            : const Border(bottom: BorderSide(color: Charte.bordure)),
      ),
      child: Row(
        children: [
          Avatar('${h['organisme'] ?? '?'}', taille: 42),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${h['organisme'] ?? 'Organisme inconnu'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 15.5,
                    fontWeight: FontWeight.w700,
                    color: Charte.texte,
                  ),
                ),
                Text(
                  '${h['question'] ?? t.$1}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontSize: 14, color: Charte.texteDoux),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Icon(t.$2, color: t.$3, size: 20),
              const SizedBox(height: 4),
              Text(
                heureFr(h['date'] as String?),
                style: const TextStyle(fontSize: 12.5, color: Charte.texteDoux),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

// ------------------------------------------------- À valider et accords

class EcranDemandes extends StatefulWidget {
  const EcranDemandes({super.key});

  @override
  State<EcranDemandes> createState() => _EtatDemandes();
}

class _EtatDemandes extends State<EcranDemandes> {
  int _vue = 0;

  Future<void> _decider(
    Map<String, dynamic> c,
    String action,
    String message,
  ) async {
    final messager = ScaffoldMessenger.of(context);
    try {
      await validerAvecAnimation(
        context,
        Depot.instance.decider(c['id'] as Object, action),
        message.replaceAll('.', ''),
      );
      HapticFeedback.mediumImpact();
      messager.showSnackBar(
        SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
      );
    } on ErreurApi catch (e) {
      messager.showSnackBar(
        SnackBar(content: Text(e.message), behavior: SnackBarBehavior.floating),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final depot = Depot.instance;
    return ListenableBuilder(
      listenable: depot,
      builder: (context, _) {
        final n = depot.aValider.length;
        return PageDefilante(
          titre: 'Vos accords',
          sousTitre:
              'Vous décidez qui peut vérifier une information vous concernant.',
          onRefresh: depot.charger,
          enteteSupplementaire: _Bascule(
            vue: _vue,
            etiquettes: ['À valider${n > 0 ? ' ($n)' : ''}', 'Mes accords'],
            onChange: (v) => setState(() => _vue = v),
          ),
          enfants: [
            _selonEtat(
              depot,
              () => _vue == 0 ? _aValider(depot) : _accords(depot),
            ),
          ],
        );
      },
    );
  }

  List<Widget> _aValider(Depot depot) {
    if (depot.aValider.isEmpty) {
      return [
        const EtatVide(
          icone: Icons.check_rounded,
          titre: 'Tout est à jour',
          texte: 'Aucune demande n’attend votre accord. Vous serez prévenu ici dès qu’un organisme en a besoin.',
          couleur: Charte.vert,
        ),
      ];
    }
    return [
      for (var i = 0; i < depot.aValider.length; i++)
        Padding(
          padding: const EdgeInsets.only(bottom: 16),
          child: Apparition(
            rang: i,
            child: _CarteDemande(c: depot.aValider[i], onDecider: _decider),
          ),
        ),
    ];
  }

  List<Widget> _accords(Depot depot) {
    if (depot.accords.isEmpty) {
      return [
        const EtatVide(
          icone: Icons.handshake_rounded,
          titre: 'Aucun accord pour l’instant',
          texte: 'Vos décisions apparaîtront ici, et vous pourrez retirer un accord à tout moment.',
        ),
      ];
    }
    return [
      for (var i = 0; i < depot.accords.length; i++)
        Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: Apparition(
            rang: i,
            child: _CarteAccord(
              c: depot.accords[i],
              onRetirer: () =>
                  _decider(depot.accords[i], 'retirer', 'Accord retiré.'),
            ),
          ),
        ),
    ];
  }
}

class _Bascule extends StatelessWidget {
  const _Bascule({
    required this.vue,
    required this.etiquettes,
    required this.onChange,
  });
  final int vue;
  final List<String> etiquettes;
  final ValueChanged<int> onChange;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(99),
        boxShadow: [
          BoxShadow(
            color: Charte.petrole.withValues(alpha: 0.12),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          for (var i = 0; i < etiquettes.length; i++)
            Expanded(
              child: Semantics(
                button: true,
                selected: vue == i,
                child: GestureDetector(
                  onTap: () => onChange(i),
                  behavior: HitTestBehavior.opaque,
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 220),
                    curve: Curves.easeOutCubic,
                    height: 46,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: vue == i ? Charte.bleu : Colors.transparent,
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Text(
                      etiquettes[i],
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: vue == i ? Colors.white : Charte.texteDoux,
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _CarteDemande extends StatelessWidget {
  const _CarteDemande({required this.c, required this.onDecider});
  final Map<String, dynamic> c;
  final Future<void> Function(Map<String, dynamic>, String, String) onDecider;

  @override
  Widget build(BuildContext context) {
    final sensible = c['sensibilite'] == 'elevee';
    return Carte(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 14),
            child: Row(
              children: [
                Avatar('${c['organisme']}', taille: 50),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${c['organisme']}',
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w700,
                          color: Charte.texte,
                        ),
                      ),
                      const Text(
                        'souhaite vérifier',
                        style: TextStyle(
                          fontSize: 13.5,
                          color: Charte.texteDoux,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(
            width: double.infinity,
            margin: const EdgeInsets.symmetric(horizontal: 18),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Charte.fond,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${c['question']}',
                  style: const TextStyle(
                    fontSize: 21,
                    height: 1.2,
                    fontWeight: FontWeight.w700,
                    color: Charte.marine,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Pour : ${c['finalite']}',
                  style: const TextStyle(
                    fontSize: 14.5,
                    height: 1.35,
                    color: Charte.texteDoux,
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 14, 18, 0),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Etiquette(
                  'À décider ${delaiRestant(c['expire_le'] as String?)}',
                  icone: Icons.timer_outlined,
                  fond: Charte.jaune,
                  couleur: const Color(0xFF3A2A00),
                ),
                if (sensible)
                  const Etiquette(
                    'Donnée sensible',
                    icone: Icons.lock_rounded,
                    fond: Color(0xFFFFE4E6),
                    couleur: Color(0xFFB00E1B),
                  ),
                const Etiquette(
                  'Réponse : oui ou non',
                  icone: Icons.help_outline_rounded,
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                      padding: const EdgeInsets.symmetric(horizontal: 6),
                      foregroundColor: const Color(0xFFB00E1B),
                      side: const BorderSide(
                        color: Color(0xFFF0A3AA),
                        width: 1.5,
                      ),
                    ),
                    onPressed: () =>
                        onDecider(c, 'refuser', 'Demande refusée.'),
                    child: const Text('Refuser'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  flex: 2,
                  child: FilledButton.icon(
                    style: FilledButton.styleFrom(
                      minimumSize: const Size.fromHeight(52),
                      backgroundColor: Charte.vert,
                    ),
                    onPressed: () =>
                        onDecider(c, 'accepter', 'Accord enregistré.'),
                    icon: const Icon(Icons.check_rounded),
                    label: const Text('Accepter'),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CarteAccord extends StatelessWidget {
  const _CarteAccord({required this.c, required this.onRetirer});
  final Map<String, dynamic> c;
  final VoidCallback onRetirer;

  @override
  Widget build(BuildContext context) {
    final statut = c['statut'] as String;
    final (etat, libelle) = switch (statut) {
      'accepte' => (EtatDemande.confirmee, 'Accord actif'),
      'refuse' => (EtatDemande.refusee, 'Refusé'),
      'retire' => (EtatDemande.refusee, 'Retiré'),
      _ => (EtatDemande.enAttente, 'Expiré'),
    };
    return Carte(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Avatar('${c['organisme']}'),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${c['organisme']}',
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: Charte.texte,
                      ),
                    ),
                    Text(
                      '${c['question']}',
                      style: const TextStyle(
                        fontSize: 14.5,
                        color: Charte.texteDoux,
                      ),
                    ),
                  ],
                ),
              ),
              Pastille(etat, libelle),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            '${c['finalite']}${c['decide_le'] != null ? ' · ${dateFr(c['decide_le'] as String?)}' : ''}',
            style: const TextStyle(fontSize: 13.5, color: Charte.texteDoux),
          ),
          if (statut == 'accepte') ...[
            const SizedBox(height: 12),
            OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
              ),
              onPressed: onRetirer,
              icon: const Icon(Icons.undo_rounded),
              label: const Text('Retirer mon accord'),
            ),
          ],
        ],
      ),
    );
  }
}

// ----------------------------------------------------------------- Messages

/// Les messages envoyés par KUMA BANA pour prévenir qu'un accord est attendu,
/// présentés comme dans une messagerie. Jamais de donnée personnelle dedans.
class EcranMessages extends StatelessWidget {
  const EcranMessages({super.key});

  @override
  Widget build(BuildContext context) {
    final depot = Depot.instance;
    return Scaffold(
      backgroundColor: Charte.fond,
      body: ListenableBuilder(
        listenable: depot,
        builder: (context, _) => PageDefilante(
          titre: 'Messages',
          sousTitre:
              'Les alertes envoyées quand un organisme attend votre accord.',
          onRefresh: depot.charger,
          actions: [
            BoutonEntete(
              icone: Icons.close_rounded,
              libelle: 'Fermer',
              onTap: () => Navigator.of(context).pop(),
            ),
          ],
          enfants: [
            _selonEtat(depot, () {
              if (depot.messages.isEmpty) {
                return [
                  const EtatVide(
                    icone: Icons.sms_rounded,
                    titre: 'Aucun message',
                    texte: 'Vous serez prévenu ici et par SMS dès qu’un organisme attend votre accord.',
                    couleur: Charte.bleu,
                  ),
                ];
              }
              return [
                const SizedBox(height: 10),
                for (var i = 0; i < depot.messages.length; i++)
                  Apparition(
                    rang: i,
                    child: _BulleMessage(depot.messages[i]),
                  ),
              ];
            }),
          ],
        ),
      ),
    );
  }
}

class _BulleMessage extends StatelessWidget {
  const _BulleMessage(this.m);
  final Map<String, dynamic> m;

  @override
  Widget build(BuildContext context) {
    final date = m['envoye_le'] as String?;
    // Le code à donner à l'agent est dans le texte du SMS : on le met en évidence.
    final code = RegExp(r': (\d{6}) \(valable').firstMatch('${m['message']}')?.group(1);
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(left: 6, bottom: 5),
            child: Text(
              'KUMA BANA · ${libelleJour(date)} ${heureFr(date)}',
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: Charte.texteDoux,
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: const BoxDecoration(
              color: Color(0xFFDAF0FA),
              borderRadius: BorderRadius.only(
                topLeft: Radius.circular(6),
                topRight: Radius.circular(18),
                bottomLeft: Radius.circular(18),
                bottomRight: Radius.circular(18),
              ),
            ),
            child: Text(
              '${m['message'] ?? ''}',
              style: const TextStyle(
                fontSize: 15,
                height: 1.4,
                color: Charte.texte,
              ),
            ),
          ),
          if (code != null)
            Padding(
              padding: const EdgeInsets.only(top: 8, left: 6),
              child: Semantics(
                label: 'Code à donner à l’agent : ${code.split('').join(' ')}',
                child: ExcludeSemantics(
                  child: Row(
                    children: [
                      const Icon(Icons.pin_rounded, color: Charte.bleu, size: 20),
                      const SizedBox(width: 8),
                      Text(
                        '${code.substring(0, 3)} ${code.substring(3)}',
                        style: const TextStyle(
                          fontSize: 26,
                          letterSpacing: 2,
                          fontWeight: FontWeight.w700,
                          color: Charte.marine,
                        ),
                      ),
                      const SizedBox(width: 10),
                      const Text(
                        'à donner à l’agent',
                        style: TextStyle(fontSize: 13.5, color: Charte.texteDoux),
                      ),
                    ],
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// --------------------------------------------------------------- Historique

class EcranHistorique extends StatelessWidget {
  const EcranHistorique({super.key});

  @override
  Widget build(BuildContext context) {
    final depot = Depot.instance;
    return ListenableBuilder(
      listenable: depot,
      builder: (context, _) => PageDefilante(
        titre: 'Qui a consulté\nmes données ?',
        sousTitre: 'Chaque consultation est enregistrée et visible ici.',
        onRefresh: depot.charger,
        enfants: [
          _selonEtat(depot, () {
            if (depot.historique.isEmpty) {
              return [
                const EtatVide(
                  icone: Icons.shield_rounded,
                  titre: 'Rien à signaler',
                  texte: 'Personne n’a consulté vos données pour le moment.',
                  couleur: Charte.vert,
                ),
              ];
            }
            final lignes = <Widget>[];
            String? courant;
            for (var i = 0; i < depot.historique.length; i++) {
              final h = depot.historique[i];
              final jour = libelleJour(h['date'] as String?);
              if (jour != courant) {
                courant = jour;
                lignes.add(
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(4, 8, 0, 10),
                      child: Text(
                        jour.toUpperCase(),
                        style: const TextStyle(
                          fontSize: 12.5,
                          letterSpacing: 0.8,
                          fontWeight: FontWeight.w700,
                          color: Charte.texteDoux,
                        ),
                      ),
                    ),
                  ),
                );
              }
              lignes.add(
                Apparition(
                  rang: i,
                  child: _EvenementFrise(
                    h,
                    dernier: i == depot.historique.length - 1,
                  ),
                ),
              );
            }
            return lignes;
          }),
        ],
      ),
    );
  }
}

class _EvenementFrise extends StatelessWidget {
  const _EvenementFrise(this.h, {required this.dernier});
  final Map<String, dynamic> h;
  final bool dernier;

  @override
  Widget build(BuildContext context) {
    final t =
        _typesHistorique[h['type']] ??
        ('Événement', Icons.info_rounded, Charte.bleu);
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 44,
            child: Column(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: t.$3,
                    boxShadow: [
                      BoxShadow(
                        color: t.$3.withValues(alpha: 0.35),
                        blurRadius: 12,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Icon(t.$2, color: Colors.white, size: 20),
                ),
                if (!dernier)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 4),
                      color: Charte.bordure,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: 14),
              child: Carte(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            '${h['organisme'] ?? 'Organisme inconnu'}',
                            style: const TextStyle(
                              fontSize: 15.5,
                              fontWeight: FontWeight.w700,
                              color: Charte.texte,
                            ),
                          ),
                        ),
                        Text(
                          heureFr(h['date'] as String?),
                          style: const TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
                            color: Charte.texteDoux,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      t.$1,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: t.$3 == const Color(0xFFC99700)
                            ? const Color(0xFF8A6600)
                            : t.$3,
                      ),
                    ),
                    if (h['question'] != null) ...[
                      const SizedBox(height: 6),
                      Text(
                        '${h['question']}',
                        style: const TextStyle(
                          fontSize: 15,
                          height: 1.3,
                          color: Charte.texte,
                        ),
                      ),
                    ],
                    if (h['finalite'] != null) ...[
                      const SizedBox(height: 8),
                      Etiquette('${h['finalite']}', icone: Icons.flag_rounded),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ------------------------------------------------------------------ Preuves

class EcranPreuves extends StatelessWidget {
  const EcranPreuves({super.key});

  @override
  Widget build(BuildContext context) {
    final depot = Depot.instance;
    return ListenableBuilder(
      listenable: depot,
      builder: (context, _) => PageDefilante(
        titre: 'Mes preuves',
        sousTitre: 'Des attestations signées, vérifiables par n’importe qui avec leur code.',
        onRefresh: depot.charger,
        enfants: [
          _selonEtat(depot, () {
            if (depot.preuves.isEmpty) {
              return [
                const EtatVide(
                  icone: Icons.qr_code_2_rounded,
                  titre: 'Pas encore de preuve',
                  texte: 'Quand une administration obtient une réponse à votre sujet, l’attestation signée apparaît ici avec son code.',
                ),
              ];
            }
            return [
              for (var i = 0; i < depot.preuves.length; i++)
                Padding(
                  padding: const EdgeInsets.only(bottom: 16),
                  child: Apparition(
                    rang: i,
                    child: _CartePreuve(depot.preuves[i]),
                  ),
                ),
            ];
          }),
        ],
      ),
    );
  }
}

/// Une attestation, présentée comme une carte de portefeuille.
class _CartePreuve extends StatelessWidget {
  const _CartePreuve(this.p);
  final Map<String, dynamic> p;

  @override
  Widget build(BuildContext context) {
    final revoquee = p['revoquee'] == true;
    return Semantics(
      button: true,
      label:
          'Attestation ${p['question']}, ${p['organisme']}. Afficher le code.',
      child: GestureDetector(
        onTap: () => _afficherCode(context),
        child: Container(
          clipBehavior: Clip.antiAlias,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(26),
            gradient: revoquee
                ? const LinearGradient(
                    colors: [Color(0xFF5B6770), Color(0xFF8A969E)],
                  )
                : Charte.degrade,
            boxShadow: [
              BoxShadow(
                color: (revoquee ? Colors.black : Charte.marine).withValues(
                  alpha: 0.28,
                ),
                blurRadius: 28,
                offset: const Offset(0, 14),
              ),
            ],
          ),
          child: Stack(
            children: [
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          width: 46,
                          height: 46,
                          padding: const EdgeInsets.all(7),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Image.asset(
                            'assets/kumabana-mark.png',
                            excludeFromSemantics: true,
                          ),
                        ),
                        const Spacer(),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.22),
                            borderRadius: BorderRadius.circular(99),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                revoquee
                                    ? Icons.block_rounded
                                    : Icons.verified_rounded,
                                color: Colors.white,
                                size: 16,
                              ),
                              const SizedBox(width: 6),
                              Text(
                                revoquee ? 'Révoquée' : 'Valide',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 13,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 26),
                    Text(
                      '${p['question']}',
                      style: const TextStyle(
                        fontSize: 26,
                        height: 1.12,
                        fontWeight: FontWeight.w700,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${p['organisme']}',
                      style: const TextStyle(
                        fontSize: 15.5,
                        color: Color(0xE6FFFFFF),
                      ),
                    ),
                    const SizedBox(height: 22),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'VALIDE JUSQU’AU',
                                style: TextStyle(
                                  fontSize: 11,
                                  letterSpacing: 0.8,
                                  fontWeight: FontWeight.w700,
                                  color: Color(0xB3FFFFFF),
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                dateFr(
                                  p['valide_jusqu_au'] as String?,
                                  heure: false,
                                ),
                                style: const TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.white,
                                ),
                              ),
                            ],
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 10,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: const Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.qr_code_2_rounded,
                                color: Charte.marine,
                                size: 22,
                              ),
                              SizedBox(width: 8),
                              Text(
                                'Afficher',
                                style: TextStyle(
                                  color: Charte.marine,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14.5,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _afficherCode(BuildContext context) {
    HapticFeedback.selectionClick();
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 44,
                height: 5,
                decoration: BoxDecoration(
                  color: Charte.bordure,
                  borderRadius: BorderRadius.circular(9),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                '${p['question']}',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                  color: Charte.marine,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                '${p['organisme']}',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 15, color: Charte.texteDoux),
              ),
              const SizedBox(height: 20),
              Semantics(
                label: 'Code QR de l’attestation ${p['reference']}',
                child: Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: Charte.bordure, width: 2),
                  ),
                  child: QrImageView(
                    data: '${p['jeton']}',
                    size: 260,
                    backgroundColor: Colors.white,
                    eyeStyle: const QrEyeStyle(
                      eyeShape: QrEyeShape.square,
                      color: Charte.marine,
                    ),
                    dataModuleStyle: const QrDataModuleStyle(
                      dataModuleShape: QrDataModuleShape.square,
                      color: Charte.marine,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Réf. ${p['reference']}',
                style: const TextStyle(
                  fontFamily: 'monospace',
                  fontSize: 13,
                  color: Charte.texteDoux,
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Présentez ce code : il se vérifie sans compte.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 14.5, color: Charte.texteDoux),
              ),
              const SizedBox(height: 18),
              OutlinedButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Fermer'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
