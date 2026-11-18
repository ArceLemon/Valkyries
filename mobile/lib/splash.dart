import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import 'charte.dart';

/// Écran de chargement : fond clair, le logo qui apparaît en douceur,
/// et une fine barre aux couleurs du pays qui avance.
class EcranSplash extends StatefulWidget {
  const EcranSplash({super.key, this.message = 'Chargement en cours'});
  final String message;

  @override
  State<EcranSplash> createState() => _EtatSplash();
}

class _EtatSplash extends State<EcranSplash> with TickerProviderStateMixin {
  late final AnimationController _entree = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..forward();
  late final AnimationController _boucle = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1600),
  )..repeat();

  @override
  void dispose() {
    _entree.dispose();
    _boucle.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final reduit = MediaQuery.of(context).disableAnimations;
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark,
      child: Semantics(
        label: widget.message,
        liveRegion: true,
        child: Material(
          color: Colors.white,
          child: Container(
            width: double.infinity,
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.white, Color(0xFFE4F3FB)],
              ),
            ),
            child: AnimatedBuilder(
              animation: Listenable.merge([_entree, _boucle]),
              builder: (context, _) {
                final e = Curves.easeOutCubic.transform(_entree.value);
                return Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Spacer(flex: 5),
                    Opacity(
                      opacity: e,
                      child: Transform.translate(
                        offset: Offset(0, 24 * (1 - e)),
                        child: Transform.scale(
                          scale: 0.9 + 0.1 * e,
                          child: Image.asset(
                            'assets/kumabana.png',
                            width: 250,
                            semanticLabel: 'KUMA BANA',
                          ),
                        ),
                      ),
                    ),
                    const Spacer(flex: 4),
                    Opacity(
                      opacity: e,
                      child: ExcludeSemantics(
                        child: _barre(reduit ? 0.5 : _boucle.value),
                      ),
                    ),
                    const SizedBox(height: 56),
                  ],
                );
              },
            ),
          ),
        ),
      ),
    );
  }

  Widget _barre(double t) {
    return Container(
      width: 160,
      height: 5,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: Charte.bordure,
        borderRadius: BorderRadius.circular(99),
      ),
      child: LayoutBuilder(
        builder: (context, c) {
          final p = Curves.easeInOutCubic.transform(t);
          final l = c.maxWidth * 0.45;
          return Stack(
            children: [
              Positioned(
                left: -l + (c.maxWidth + l) * p,
                width: l,
                top: 0,
                bottom: 0,
                child: const BandeDrapeau(hauteur: 5),
              ),
            ],
          );
        },
      ),
    );
  }
}
