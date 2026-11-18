import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import 'api.dart';
import 'charte.dart';

/// Pastille ronde avec les initiales d'un organisme, d'une couleur stable.
class Avatar extends StatelessWidget {
  const Avatar(this.nom, {super.key, this.taille = 46});
  final String nom;
  final double taille;

  static const _couleurs = [
    Charte.bleu,
    Charte.petrole,
    Charte.vert,
    Color(0xFFD9485B),
    Color(0xFFC99700),
  ];

  @override
  Widget build(BuildContext context) {
    final mots = nom
        .replaceAll(RegExp(r'\(.*?\)'), '')
        .trim()
        .split(RegExp(r'\s+'))
        .where((m) => m.length > 2)
        .toList();
    final initiales =
        (mots.isEmpty ? nom : mots.take(2).map((m) => m[0]).join())
            .toUpperCase();
    final c =
        _couleurs[nom.codeUnits.fold<int>(0, (a, b) => a + b) %
            _couleurs.length];
    return Container(
      width: taille,
      height: taille,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [c, Color.lerp(c, Colors.white, 0.28)!],
        ),
      ),
      child: ExcludeSemantics(
        child: Text(
          initiales,
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: taille * 0.34,
          ),
        ),
      ),
    );
  }
}

/// Apparition douce, décalée selon la position dans la liste.
class Apparition extends StatelessWidget {
  const Apparition({super.key, required this.child, this.rang = 0});
  final Widget child;
  final int rang;

  @override
  Widget build(BuildContext context) {
    if (MediaQuery.of(context).disableAnimations) return child;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: 420 + rang.clamp(0, 6) * 70),
      curve: Curves.easeOutCubic,
      builder: (context, v, c) => Opacity(
        opacity: v,
        child: Transform.translate(offset: Offset(0, 22 * (1 - v)), child: c),
      ),
      child: child,
    );
  }
}

/// Carte blanche à ombre douce.
class Carte extends StatelessWidget {
  const Carte({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.onTap,
    this.couleur,
  });
  final Widget child;
  final EdgeInsets padding;
  final VoidCallback? onTap;
  final Color? couleur;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: couleur ?? Colors.white,
        borderRadius: BorderRadius.circular(Charte.rayonPanneau),
        border: Border.all(color: Charte.bordure),
      ),
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          borderRadius: BorderRadius.circular(Charte.rayonPanneau),
          onTap: onTap,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );
  }
}

/// Petite étiquette colorée : icône + texte.
class Etiquette extends StatelessWidget {
  const Etiquette(
    this.texte, {
    super.key,
    this.icone,
    this.fond = const Color(0xFFDAF0FA),
    this.couleur = const Color(0xFF054A66),
  });
  final String texte;
  final IconData? icone;
  final Color fond;
  final Color couleur;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: fond,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icone != null) ...[
            Icon(icone, size: 14, color: couleur),
            const SizedBox(width: 5),
          ],
          Flexible(
            child: Text(
              texte,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: couleur,
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}

/// Page défilante, sobre : un grand titre sur fond clair, puis le contenu.
class PageDefilante extends StatelessWidget {
  const PageDefilante({
    super.key,
    required this.titre,
    this.sousTitre,
    required this.enfants,
    required this.onRefresh,
    this.actions = const [],
    this.enteteSupplementaire,
    this.avant,
  });

  final String titre;
  final String? sousTitre;
  final List<Widget> enfants;
  final Future<void> Function() onRefresh;
  final List<Widget> actions;
  final Widget? enteteSupplementaire;

  /// Élément à gauche du titre (avatar).
  final Widget? avant;

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: RefreshIndicator(
        onRefresh: onRefresh,
        color: Charte.bleu,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    Charte.gouttiere,
                    16,
                    Charte.gouttiere,
                    4,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          if (avant != null) ...[
                            avant!,
                            const SizedBox(width: 12),
                          ],
                          Expanded(
                            child: Semantics(
                              header: true,
                              child: Text(
                                titre,
                                style: const TextStyle(
                                  fontSize: 27,
                                  height: 1.15,
                                  fontWeight: FontWeight.w700,
                                  color: Charte.marine,
                                ),
                              ),
                            ),
                          ),
                          ...actions,
                        ],
                      ),
                      if (sousTitre != null) ...[
                        const SizedBox(height: 6),
                        Text(
                          sousTitre!,
                          style: const TextStyle(
                            fontSize: 15,
                            height: 1.4,
                            color: Charte.texteDoux,
                          ),
                        ),
                      ],
                      if (enteteSupplementaire != null) ...[
                        const SizedBox(height: 16),
                        enteteSupplementaire!,
                      ],
                    ],
                  ),
                ),
              ),
            ),
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(
                Charte.gouttiere,
                8,
                Charte.gouttiere,
                28,
              ),
              sliver: SliverList(delegate: SliverChildListDelegate(enfants)),
            ),
          ],
        ),
      ),
    );
  }
}

/// Bouton rond d'en-tête (déconnexion...).
class BoutonEntete extends StatelessWidget {
  const BoutonEntete({
    super.key,
    required this.icone,
    required this.libelle,
    required this.onTap,
  });
  final IconData icone;
  final String libelle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: libelle,
      child: Semantics(
        button: true,
        label: libelle,
        child: InkResponse(
          onTap: onTap,
          radius: 26,
          child: Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: Colors.white,
              shape: BoxShape.circle,
              border: Border.all(color: Charte.bordure),
            ),
            child: Icon(icone, color: Charte.marine, size: 21),
          ),
        ),
      ),
    );
  }
}

/// Titre de section avec lien facultatif.
class TitreSection extends StatelessWidget {
  const TitreSection(this.texte, {super.key, this.action, this.onAction});
  final String texte;
  final String? action;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(2, 22, 2, 12),
      child: Row(
        children: [
          Expanded(
            child: Semantics(
              header: true,
              child: Text(
                texte,
                style: const TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w700,
                  color: Charte.texte,
                ),
              ),
            ),
          ),
          if (action != null)
            TextButton(
              onPressed: onAction,
              style: TextButton.styleFrom(
                foregroundColor: Charte.bleu,
                minimumSize: const Size(48, 44),
              ),
              child: Text(
                action!,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
        ],
      ),
    );
  }
}

/// État vide : un grand pictogramme et une phrase.
class EtatVide extends StatelessWidget {
  const EtatVide({
    super.key,
    required this.icone,
    required this.titre,
    required this.texte,
    this.couleur = Charte.bleu,
  });
  final IconData icone;
  final String titre;
  final String texte;
  final Color couleur;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 12),
      child: Column(
        children: [
          Container(
            width: 112,
            height: 112,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: couleur.withValues(alpha: 0.12),
            ),
            child: Center(
              child: Container(
                width: 78,
                height: 78,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: couleur,
                ),
                child: Icon(icone, color: Colors.white, size: 40),
              ),
            ),
          ),
          const SizedBox(height: 20),
          Text(
            titre,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w700,
              color: Charte.texte,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            texte,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 15,
              height: 1.45,
              color: Charte.texteDoux,
            ),
          ),
        ],
      ),
    );
  }
}

/// Chargement : des formes qui annoncent le contenu, avec un reflet qui passe.
class Squelettes extends StatefulWidget {
  const Squelettes({super.key, this.nombre = 3, this.hauteur = 120});
  final int nombre;
  final double hauteur;

  @override
  State<Squelettes> createState() => _EtatSquelettes();
}

class _EtatSquelettes extends State<Squelettes>
    with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1300),
  )..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Chargement en cours',
      liveRegion: true,
      child: ExcludeSemantics(
        child: AnimatedBuilder(
          animation: _c,
          builder: (context, _) => Column(
            children: [
              for (var i = 0; i < widget.nombre; i++)
                Container(
                  height: widget.hauteur,
                  margin: const EdgeInsets.only(bottom: 14),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(Charte.rayonPanneau),
                    gradient: LinearGradient(
                      begin: Alignment(-1.4 + _c.value * 2.8, 0),
                      end: Alignment(-0.4 + _c.value * 2.8, 0),
                      colors: const [
                        Color(0xFFDDEEF7),
                        Color(0xFFF3FAFE),
                        Color(0xFFDDEEF7),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Message d'erreur réseau avec bouton de nouvel essai.
class ErreurReseau extends StatelessWidget {
  const ErreurReseau({super.key, required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        EtatVide(
          icone: Icons.cloud_off_rounded,
          titre: 'Connexion impossible',
          texte: message,
          couleur: Charte.rouge,
        ),
        OutlinedButton(onPressed: onRetry, child: const Text('Réessayer')),
      ],
    );
  }
}

/// « Aujourd'hui », « Hier » ou « 16 novembre 2026 ».
String libelleJour(String? iso) {
  final d = DateTime.tryParse(iso ?? '')?.toLocal();
  if (d == null) return '';
  final auj = DateTime.now();
  final j = DateTime(d.year, d.month, d.day);
  final delta = DateTime(auj.year, auj.month, auj.day).difference(j).inDays;
  if (delta == 0) return "Aujourd'hui";
  if (delta == 1) return 'Hier';
  return dateFr(iso, heure: false);
}

String heureFr(String? iso) {
  final d = DateTime.tryParse(iso ?? '')?.toLocal();
  if (d == null) return '';
  return '${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}';
}

/// « dans 2 j », « dans 5 h », « bientôt ».
String delaiRestant(String? iso) {
  final d = DateTime.tryParse(iso ?? '')?.toLocal();
  if (d == null) return '';
  final reste = d.difference(DateTime.now());
  if (reste.isNegative) return 'expirée';
  if (reste.inDays >= 1) return 'dans ${reste.inDays} j';
  if (reste.inHours >= 1) return 'dans ${reste.inHours} h';
  return 'dans ${reste.inMinutes.clamp(1, 59)} min';
}
