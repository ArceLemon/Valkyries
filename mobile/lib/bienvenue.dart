import 'dart:math' as math;

import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import 'charte.dart';

/// Premier écran : fond bleu, une grande phrase, le logo, deux gros boutons.
class PageBienvenue extends StatefulWidget {
  const PageBienvenue({
    super.key,
    required this.onConnexion,
    required this.onInscription,
  });
  final VoidCallback onConnexion;
  final VoidCallback onInscription;

  @override
  State<PageBienvenue> createState() => _EtatBienvenue();
}

class _EtatBienvenue extends State<PageBienvenue>
    with SingleTickerProviderStateMixin {
  late final AnimationController _flotte = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 5),
  )..repeat();

  @override
  void dispose() {
    _flotte.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduit = MediaQuery.of(context).disableAnimations;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: Charte.marine,
        body: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0xFF012F45), Charte.marine, Charte.petrole],
              stops: [0, 0.5, 1],
            ),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 20, 24, 16),
              child: Column(
                children: [
                  const Text(
                    'KUMA BANA',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 3,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const SizedBox(width: 30, child: BandeDrapeau(hauteur: 3)),
                  const Spacer(flex: 2),
                  const Text(
                    'VOTRE',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.w300,
                      letterSpacing: 4,
                    ),
                  ),
                  const Text(
                    'ADMINISTRATION',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 34,
                      height: 1.1,
                      fontWeight: FontWeight.w300,
                      letterSpacing: 1,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'EN TOUTE',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w300,
                      letterSpacing: 3,
                    ),
                  ),
                  const Text(
                    'TRANSPARENCE',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 26,
                      height: 1.2,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1,
                    ),
                  ),
                  const Spacer(flex: 2),
                  AnimatedBuilder(
                    animation: _flotte,
                    builder: (context, _) => SizedBox(
                      width: 300,
                      height: 250,
                      child: Stack(
                        alignment: Alignment.center,
                        children: [
                          _puce(
                            Icons.verified_user_rounded,
                            Charte.jaune,
                            Charte.marine,
                            -112,
                            -78,
                            0,
                            reduit,
                          ),
                          _puce(
                            Icons.visibility_rounded,
                            Colors.white,
                            Charte.bleu,
                            114,
                            -52,
                            1.6,
                            reduit,
                          ),
                          _puce(
                            Icons.qr_code_2_rounded,
                            Charte.vert,
                            Colors.white,
                            -116,
                            62,
                            3.2,
                            reduit,
                          ),
                          _puce(
                            Icons.handshake_rounded,
                            Charte.ciel,
                            Colors.white,
                            112,
                            82,
                            4.6,
                            reduit,
                          ),
                          Container(
                            width: 168,
                            height: 168,
                            padding: const EdgeInsets.all(26),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(44),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.3),
                                  blurRadius: 40,
                                  offset: const Offset(0, 20),
                                ),
                              ],
                            ),
                            child: Image.asset(
                              'assets/kumabana-mark.png',
                              semanticLabel: 'Logo KUMA BANA',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const Spacer(flex: 3),
                  FilledButton(
                    style: FilledButton.styleFrom(
                      backgroundColor: Charte.jaune,
                      foregroundColor: Charte.marine,
                      minimumSize: const Size.fromHeight(56),
                    ),
                    onPressed: widget.onConnexion,
                    child: const Text('Se connecter'),
                  ),
                  const SizedBox(height: 12),
                  FilledButton(
                    style: FilledButton.styleFrom(
                      backgroundColor: Charte.bleu,
                      foregroundColor: Colors.white,
                      minimumSize: const Size.fromHeight(56),
                    ),
                    onPressed: widget.onInscription,
                    child: const Text('Créer mon compte'),
                  ),
                  const SizedBox(height: 4),
                  TextButton(
                    style: TextButton.styleFrom(
                      foregroundColor: Colors.white,
                      minimumSize: const Size.fromHeight(48),
                    ),
                    onPressed: () => _commentCaMarche(context),
                    child: const Text('Comment ça marche'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// Petite pastille d'icône qui flotte doucement autour du logo.
  Widget _puce(
    IconData icone,
    Color fond,
    Color couleur,
    double x,
    double y,
    double phase,
    bool reduit,
  ) {
    final dy = reduit ? 0.0 : math.sin(_flotte.value * 2 * math.pi + phase) * 6;
    return Transform.translate(
      offset: Offset(x, y + dy),
      child: Transform.rotate(
        angle: x < 0 ? -0.12 : 0.12,
        child: Container(
          width: 52,
          height: 52,
          decoration: BoxDecoration(
            color: fond,
            borderRadius: BorderRadius.circular(16),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.25),
                blurRadius: 16,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: ExcludeSemantics(child: Icon(icone, color: couleur, size: 26)),
        ),
      ),
    );
  }

  void _commentCaMarche(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: Colors.white,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 14, 24, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 44,
                  height: 5,
                  decoration: BoxDecoration(
                    color: Charte.bordure,
                    borderRadius: BorderRadius.circular(9),
                  ),
                ),
              ),
              const SizedBox(height: 18),
              const Text(
                'Comment ça marche',
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w700,
                  color: Charte.marine,
                ),
              ),
              const SizedBox(height: 18),
              const _Etape(
                1,
                Charte.bleu,
                'Une administration pose une question',
                'Elle ne reçoit qu’un oui ou un non, jamais vos documents.',
              ),
              const _Etape(
                2,
                Charte.jaune,
                'Vous décidez',
                'Pour une donnée sensible, rien ne part sans votre accord.',
              ),
              const _Etape(
                3,
                Charte.vert,
                'Vous gardez la trace',
                'Chaque consultation apparaît dans votre historique.',
              ),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Compris'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Etape extends StatelessWidget {
  const _Etape(this.numero, this.couleur, this.titre, this.texte);
  final int numero;
  final Color couleur;
  final String titre;
  final String texte;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: couleur,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              '$numero',
              style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 17,
                color: couleur == Charte.jaune ? Charte.marine : Colors.white,
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  titre,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: Charte.texte,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  texte,
                  style: const TextStyle(
                    fontSize: 14.5,
                    height: 1.4,
                    color: Charte.texteDoux,
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
