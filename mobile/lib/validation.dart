import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/services.dart';
import 'package:material_ui/material_ui.dart';

import 'charte.dart';

/// Affiche une animation « chargement, puis validé » le temps d'une action.
/// Si l'action échoue, l'animation disparaît et l'erreur est relancée.
Future<T> validerAvecAnimation<T>(
  BuildContext context,
  Future<T> travail,
  String message,
) async {
  final navigateur = Navigator.of(context, rootNavigator: true);
  final termine = ValueNotifier<bool>(false);
  var ouvert = true;

  void fermer() {
    if (ouvert && navigateur.mounted) {
      ouvert = false;
      navigateur.pop();
    }
  }

  // ignore: unawaited_futures
  navigateur.push(
    PageRouteBuilder<void>(
      opaque: false,
      barrierDismissible: false,
      barrierColor: Charte.marine.withValues(alpha: 0.55),
      transitionDuration: const Duration(milliseconds: 220),
      reverseTransitionDuration: const Duration(milliseconds: 220),
      pageBuilder: (context, _, _) => PopScope(
        canPop: false,
        child: Center(
          child: _CarteValidation(termine: termine, message: message),
        ),
      ),
      transitionsBuilder: (context, animation, _, enfant) =>
          FadeTransition(opacity: animation, child: enfant),
    ),
  );

  final debut = DateTime.now();
  try {
    final resultat = await travail;
    final reste =
        const Duration(milliseconds: 700) - DateTime.now().difference(debut);
    if (!reste.isNegative) await Future<void>.delayed(reste);
    termine.value = true;
    HapticFeedback.mediumImpact();
    Future<void>.delayed(const Duration(milliseconds: 1600), fermer);
    return resultat;
  } catch (_) {
    fermer();
    rethrow;
  }
}

class _CarteValidation extends StatefulWidget {
  const _CarteValidation({required this.termine, required this.message});
  final ValueNotifier<bool> termine;
  final String message;

  @override
  State<_CarteValidation> createState() => _EtatCarteValidation();
}

class _EtatCarteValidation extends State<_CarteValidation>
    with TickerProviderStateMixin {
  late final AnimationController _rotation = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat();
  late final AnimationController _succes = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 800),
  );

  @override
  void initState() {
    super.initState();
    widget.termine.addListener(_surChangement);
  }

  void _surChangement() {
    if (widget.termine.value) {
      _rotation.stop();
      _succes.forward();
      setState(() {});
    }
  }

  @override
  void dispose() {
    widget.termine.removeListener(_surChangement);
    _rotation.dispose();
    _succes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final fini = widget.termine.value;
    return Semantics(
      liveRegion: true,
      label: fini ? widget.message : 'Validation en cours',
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(26),
        elevation: 24,
        shadowColor: Charte.marine.withValues(alpha: 0.5),
        child: Container(
          constraints: const BoxConstraints(minWidth: 280),
          padding: const EdgeInsets.fromLTRB(44, 34, 44, 30),
          child: ExcludeSemantics(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                SizedBox(
                  width: 96,
                  height: 96,
                  child: AnimatedBuilder(
                    animation: Listenable.merge([_rotation, _succes]),
                    builder: (context, _) => CustomPaint(
                      painter: _PeintreValidation(
                        rotation: _rotation.value,
                        succes: _succes.value,
                        fini: fini,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  fini ? widget.message : 'Validation en cours…',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: Charte.texte,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PeintreValidation extends CustomPainter {
  _PeintreValidation({
    required this.rotation,
    required this.succes,
    required this.fini,
  });
  final double rotation;
  final double succes;
  final bool fini;

  @override
  void paint(Canvas canvas, Size taille) {
    final centre = taille.center(Offset.zero);
    const rayon = 36.0;

    if (!fini) {
      final piste = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 9
        ..color = const Color(0xFFDAF0FA);
      canvas.drawCircle(centre, rayon, piste);
      final arc = Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 9
        ..strokeCap = StrokeCap.round
        ..color = Charte.bleu;
      canvas.drawArc(
        Rect.fromCircle(center: centre, radius: rayon),
        rotation * 2 * math.pi,
        math.pi * 0.7,
        false,
        arc,
      );
      return;
    }

    // Halo qui s'éloigne
    final halo = Curves.easeOut.transform((succes / 0.9).clamp(0.0, 1.0));
    canvas.drawCircle(
      centre,
      rayon * (0.8 + 0.75 * halo),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 5
        ..color = Charte.vert.withValues(alpha: 0.55 * (1 - halo)),
    );

    // Disque vert qui apparaît avec un léger rebond
    final pop = Curves.easeOutBack.transform((succes / 0.5).clamp(0.0, 1.0));
    canvas.drawCircle(centre, rayon * pop, Paint()..color = Charte.vert);

    // Coche tracée
    final trace = Curves.easeInOutCubic.transform(
      ((succes - 0.3) / 0.5).clamp(0.0, 1.0),
    );
    final coche = Path()
      ..moveTo(centre.dx - 17, centre.dy + 1)
      ..lineTo(centre.dx - 5, centre.dy + 13)
      ..lineTo(centre.dx + 18, centre.dy - 13);
    final metrique = coche.computeMetrics().first;
    canvas.drawPath(
      metrique.extractPath(0, metrique.length * trace),
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 8
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..color = Colors.white,
    );
  }

  @override
  bool shouldRepaint(_PeintreValidation ancien) =>
      ancien.rotation != rotation ||
      ancien.succes != succes ||
      ancien.fini != fini;
}

/// Utilisé pour tracer : évite un import inutile de ui dans les autres fichiers.
typedef MetriqueChemin = ui.PathMetric;
