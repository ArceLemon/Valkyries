import 'package:flutter_test/flutter_test.dart';
import 'package:kumabana_citoyen/charte.dart';
import 'package:kumabana_citoyen/main.dart';
import 'package:material_ui/material_ui.dart';

void main() {
  testWidgets('splash, bienvenue puis connexion', (tester) async {
    tester.view.physicalSize = const Size(1170, 2532);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(KumaBanaApp(demarrage: () async => false));
    await tester.pump(const Duration(seconds: 3));
    await tester.pump(const Duration(seconds: 1));

    // Écran de bienvenue
    expect(find.text('TRANSPARENCE'), findsOneWidget);
    expect(find.text('Comment ça marche'), findsOneWidget);

    await tester.tap(find.text('Se connecter'));
    await tester.pump(const Duration(seconds: 1));
    await tester.pump(const Duration(seconds: 1));

    // Connexion
    expect(find.text('Connectez-vous'), findsOneWidget);
    expect(find.text('Comptes de démonstration (fictifs)'), findsOneWidget);
  });

  testWidgets('les pastilles d’état disent leur état en texte', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Column(
          children: [
            Pastille(EtatDemande.confirmee, 'Confirmée'),
            Pastille(EtatDemande.enAttente, 'En attente du registre'),
            Pastille(EtatDemande.refusee, 'Refusée'),
          ],
        ),
      ),
    );
    expect(find.text('Confirmée'), findsOneWidget);
    expect(find.text('En attente du registre'), findsOneWidget);
    expect(find.text('Refusée'), findsOneWidget);
  });
}
