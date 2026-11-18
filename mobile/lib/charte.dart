import 'package:material_ui/material_ui.dart';

/// Charte graphique KUMA BANA, version lumineuse (identique au portail web).
///
/// Les couleurs viennent du logo : bleu pétrole et bleu vif (le symbole), jaune
/// et rouge (la carte du pays). Le vert est celui du drapeau : il dit « oui ».
///
/// Rôles : bleu = action et navigation, jaune = attente, rouge = refus ou panne,
/// vert = réussite. La couleur n'est jamais seule : une icône et un texte
/// disent la même chose.
abstract final class Charte {
  static const Color marine = Color(0xFF023E58);
  static const Color petrole = Color(0xFF035C75);
  static const Color bleu = Color(0xFF0A7BA2);
  static const Color ciel = Color(0xFF3FB8E8);
  static const Color jaune = Color(0xFFFCC304);
  static const Color rouge = Color(0xFFEB1525);
  static const Color vert = Color(0xFF12A150);

  static const Color fond = Color(0xFFEEF8FD);
  static const Color fondDoux = Color(0xFFE4F3FB);
  static const Color bordure = Color(0xFFCFE5F1);
  static const Color texte = Color(0xFF0A2A3B);
  static const Color texteDoux = Color(0xFF46687C);

  static const LinearGradient degrade = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [marine, petrole, bleu, ciel],
    stops: [0, 0.38, 0.7, 1],
  );

  static const double rayonControle = 14;
  static const double rayonPanneau = 20;
  static const double gouttiere = 20;

  static const String police = 'Geist';

  static ThemeData theme() {
    const schema = ColorScheme(
      brightness: Brightness.light,
      primary: bleu,
      onPrimary: Colors.white,
      primaryContainer: Color(0xFFDAF0FA),
      onPrimaryContainer: Color(0xFF054A66),
      secondary: petrole,
      onSecondary: Colors.white,
      tertiary: jaune,
      onTertiary: Color(0xFF3A2A00),
      error: Color(0xFFB00E1B),
      onError: Colors.white,
      errorContainer: Color(0xFFFFE4E6),
      onErrorContainer: Color(0xFFB00E1B),
      surface: fond,
      onSurface: texte,
      onSurfaceVariant: texteDoux,
      surfaceContainerLowest: Colors.white,
      surfaceContainerHighest: fondDoux,
      outline: Color(0xFF9CC6DD),
      outlineVariant: bordure,
    );
    return ThemeData(
      useMaterial3: true,
      colorScheme: schema,
      fontFamily: police,
      scaffoldBackgroundColor: fond,
      textTheme: const TextTheme(
        headlineMedium: TextStyle(
          fontSize: 28,
          height: 1.2,
          fontWeight: FontWeight.w700,
          color: texte,
        ),
        titleLarge: TextStyle(
          fontSize: 22,
          height: 1.25,
          fontWeight: FontWeight.w700,
          color: texte,
        ),
        titleMedium: TextStyle(
          fontSize: 17,
          height: 1.3,
          fontWeight: FontWeight.w600,
          color: texte,
        ),
        bodyLarge: TextStyle(fontSize: 16, height: 1.5, color: texte),
        bodyMedium: TextStyle(fontSize: 14, height: 1.45, color: texteDoux),
        labelLarge: TextStyle(
          fontSize: 15,
          height: 1.3,
          fontWeight: FontWeight.w600,
        ),
        labelSmall: TextStyle(
          fontSize: 12,
          height: 1.3,
          fontWeight: FontWeight.w600,
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size.fromHeight(54),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(rayonControle),
          ),
          textStyle: const TextStyle(
            fontFamily: police,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(54),
          side: const BorderSide(color: Color(0xFF9CC6DD), width: 1.5),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(rayonControle),
          ),
          textStyle: const TextStyle(
            fontFamily: police,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: Colors.white,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(rayonControle),
          borderSide: const BorderSide(color: Color(0xFF9CC6DD)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(rayonControle),
          borderSide: const BorderSide(color: Color(0xFF9CC6DD)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(rayonControle),
          borderSide: const BorderSide(color: bleu, width: 2),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: const Color(0xFFDAF0FA),
        height: 72,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (s) => TextStyle(
            fontFamily: police,
            fontSize: 12,
            fontWeight: FontWeight.w600,
            color: s.contains(WidgetState.selected) ? petrole : texteDoux,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (s) => IconThemeData(
            color: s.contains(WidgetState.selected) ? petrole : texteDoux,
          ),
        ),
      ),
    );
  }
}

/// États d'une demande ou d'un consentement, avec leurs couleurs.
enum EtatDemande { confirmee, enAttente, refusee }

class CouleursEtat {
  const CouleursEtat(this.fond, this.texte, this.icone);
  final Color fond;
  final Color texte;
  final IconData icone;
}

CouleursEtat couleursEtat(EtatDemande e) => switch (e) {
  EtatDemande.confirmee => const CouleursEtat(
    Color(0xFFDCF6E8),
    Color(0xFF0A6636),
    Icons.check_circle,
  ),
  EtatDemande.enAttente => const CouleursEtat(
    Color(0xFFFCC304),
    Color(0xFF3A2A00),
    Icons.schedule,
  ),
  EtatDemande.refusee => const CouleursEtat(
    Color(0xFFFFE4E6),
    Color(0xFFB00E1B),
    Icons.cancel,
  ),
};

/// Bande jaune, rouge, vert : les couleurs du pays.
class BandeDrapeau extends StatelessWidget {
  const BandeDrapeau({super.key, this.hauteur = 4});
  final double hauteur;

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: SizedBox(
        height: hauteur,
        child: const Row(
          children: [
            Expanded(
              flex: 34,
              child: ColoredBox(color: Charte.jaune, child: SizedBox.expand()),
            ),
            Expanded(
              flex: 33,
              child: ColoredBox(color: Charte.rouge, child: SizedBox.expand()),
            ),
            Expanded(
              flex: 33,
              child: ColoredBox(color: Charte.vert, child: SizedBox.expand()),
            ),
          ],
        ),
      ),
    );
  }
}

/// Pastille d'état : icône + texte.
class Pastille extends StatelessWidget {
  const Pastille(this.etat, this.libelle, {super.key});
  final EtatDemande etat;
  final String libelle;

  @override
  Widget build(BuildContext context) {
    final c = couleursEtat(etat);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: c.fond,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(c.icone, size: 16, color: c.texte),
          const SizedBox(width: 6),
          Text(
            libelle,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: c.texte,
            ),
          ),
        ],
      ),
    );
  }
}
