<?php

namespace Tests\Feature;

use App\Models\AccessRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Catalogue public et circuit d'habilitation : l'organisme demande, la source valide
 * avec une référence juridique et une date limite, et peut reprendre le droit.
 */
class CatalogueEtHabilitationTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    private const FINALITE = 'Immatriculation d\'une entreprise';

    public function test_le_catalogue_public_est_lisible_sans_compte(): void
    {
        $this->app['auth']->forgetGuards();
        $reponse = $this->getJson('/api/catalogue')->assertOk();

        $reponse->assertJsonCount(7, 'donnees');

        $questions = collect($reponse->json('donnees'))->keyBy('code');
        $this->assertSame('Direction de l\'état civil (démo)', $questions['majeur']['source']);
        $this->assertSame('base_legale', $questions['majeur']['regime']);
        $this->assertSame('consentement', $questions['casier_vierge']['regime']);
        $this->assertSame('elevee', $questions['casier_vierge']['sensibilite']);
        $this->assertSame(['oui', 'non', 'impossible'], $questions['majeur']['reponses']);
        $this->assertNotEmpty($questions['majeur']['description']);

        // La règle de calcul interne et les citoyens ne sont jamais exposés
        $reponse->assertJsonMissingPath('donnees.0.regle');
        $this->assertStringNotContainsString('age_gte', $reponse->getContent());
        $this->assertStringNotContainsString('BF-0001', $reponse->getContent());
    }

    public function test_la_fiche_d_une_question(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/catalogue/vivant')->assertOk()->assertJsonPath('code', 'vivant');
        $this->getJson('/api/catalogue/inconnue')->assertNotFound();
    }

    public function test_une_question_retiree_du_catalogue_disparait(): void
    {
        $id = \App\Models\Assertion::where('code', 'vivant')->value('id');
        $this->connecte('admin')->patchJson("/api/admin/catalogue/{$id}", ['publie' => false])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/catalogue')->assertJsonCount(6, 'donnees');
        $this->getJson('/api/catalogue/vivant')->assertNotFound();
    }

    public function test_demande_d_habilitation_de_bout_en_bout(): void
    {
        // 1. Le responsable demande un droit, avec sa justification
        $this->connecte('responsable.commerce')->postJson('/api/organisme/droits', [
            'question' => 'vivant',
            'finalite' => self::FINALITE,
            'justification' => 'Vérifier que le dirigeant est en vie avant l\'immatriculation.',
        ])->assertCreated()->assertJsonPath('etat', 'en_attente');

        // 2. Tant que la source n'a pas validé, le droit est sans effet
        $this->poser('agent.commerce', 'vivant', ['identifiant' => 'BF-0001'])->assertForbidden();

        // 3. Le gestionnaire du registre voit la demande et sa justification
        $enAttente = $this->connecte('gestionnaire.etat-civil')->getJson('/api/registre/acces')->assertOk();
        $enAttente->assertJsonCount(1, 'donnees');
        $this->assertStringContainsString('en vie', $enAttente->json('donnees.0.justification'));
        $id = $enAttente->json('donnees.0.id');

        // 4. Il ne peut pas valider sans consigner la référence juridique
        $this->connecte('gestionnaire.etat-civil')->postJson("/api/registre/acces/{$id}/valider", [])->assertStatus(422);

        $this->connecte('gestionnaire.etat-civil')->postJson("/api/registre/acces/{$id}/valider", [
            'reference_juridique' => 'Convention d\'échange n° 2026-17',
            'expire_le' => now()->addMonths(6)->toDateTimeString(),
        ])->assertOk();

        // 5. Le droit est maintenant effectif
        $this->poser('agent.commerce', 'vivant', ['identifiant' => 'BF-0001'])
            ->assertCreated()->assertJsonPath('reponse', 'oui');

        // 6. Le responsable voit l'état, la référence et l'échéance
        $droits = $this->connecte('responsable.commerce')->getJson('/api/organisme/droits')->assertOk();
        $vivant = collect($droits->json('donnees'))->firstWhere('question', 'vivant');
        $this->assertSame('valide', $vivant['etat']);
        $this->assertSame('Convention d\'échange n° 2026-17', $vivant['reference_juridique']);
        $this->assertNotNull($vivant['expire_le']);

        // Tout est dans le journal
        foreach (['droit.demande', 'droit.valide'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_une_demande_en_double_ou_inconnue_est_refusee(): void
    {
        $corps = ['question' => 'vivant', 'finalite' => self::FINALITE, 'justification' => 'Une justification suffisante.'];

        $this->connecte('responsable.commerce')->postJson('/api/organisme/droits', $corps)->assertCreated();
        $this->connecte('responsable.commerce')->postJson('/api/organisme/droits', $corps)->assertStatus(409);

        $this->connecte('responsable.commerce')
            ->postJson('/api/organisme/droits', ['question' => 'inconnue'] + $corps)->assertStatus(422);
        $this->connecte('responsable.commerce')
            ->postJson('/api/organisme/droits', ['justification' => 'court'] + $corps)->assertStatus(422);

        // Un agent ne peut pas demander de droits : c'est l'affaire du responsable
        $this->connecte('agent.commerce')->postJson('/api/organisme/droits', $corps)->assertForbidden();
    }

    public function test_un_droit_expire_est_refuse(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        AccessRule::query()->update(['expire_le' => now()->addDay()]);
        $this->travel(2)->days();

        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertForbidden();

        $droits = $this->connecte('responsable.commerce')->getJson('/api/organisme/droits')->assertOk();
        $this->assertSame('expire', collect($droits->json('donnees'))->firstWhere('question', 'majeur')['etat']);
    }

    public function test_la_source_peut_retirer_un_droit_deja_accorde(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $valides = $this->connecte('gestionnaire.etat-civil')->getJson('/api/registre/acces?statut=valide')->assertOk();
        $droit = collect($valides->json('donnees'))->first(
            fn ($d) => $d['organisme'] === 'Registre du commerce (démo)' && $d['question'] === 'Est majeur',
        );
        $this->assertNotNull($droit);
        $this->assertStringContainsString('DEMO-2026-01', $droit['reference_juridique']);

        $this->connecte('gestionnaire.etat-civil')->postJson("/api/registre/acces/{$droit['id']}/retirer")->assertOk();

        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'droit.retire']);
    }

    public function test_un_gestionnaire_ne_decide_que_pour_son_registre(): void
    {
        $this->connecte('responsable.commerce')->postJson('/api/organisme/droits', [
            'question' => 'vivant', 'finalite' => self::FINALITE, 'justification' => 'Une justification suffisante.',
        ])->assertCreated();
        $id = AccessRule::latest('id')->value('id');

        // Le gestionnaire du casier n'est pas concerné par une question d'état civil
        $this->connecte('gestionnaire.casier')
            ->postJson("/api/registre/acces/{$id}/valider", ['reference_juridique' => 'x'])->assertNotFound();
        $this->connecte('gestionnaire.casier')->postJson("/api/registre/acces/{$id}/refuser")->assertNotFound();
    }

    public function test_les_droits_disponibles_alimentent_le_formulaire_de_demande(): void
    {
        $reponse = $this->connecte('agent.commerce')->getJson('/api/droits-disponibles')->assertOk();

        $questions = collect($reponse->json('donnees'))->keyBy('question');
        $this->assertEqualsCanonicalizing(['majeur', 'nationalite_burkinabe', 'casier_vierge'], $questions->keys()->all());
        $this->assertSame('consentement', $questions['casier_vierge']['regime']);
        $this->assertSame(self::FINALITE, $questions['majeur']['finalite']);

        // Un droit non validé n'y figure pas : la mairie a cinq droits actifs (2 pour la délivrance
        // d'un document, 3 pour les listes électorales), mais pas « certificat de nationalité valide »
        $mairie = $this->connecte('agent.mairie')->getJson('/api/droits-disponibles')->assertJsonCount(5, 'donnees');
        $this->assertNotContains('certificat_nationalite_valide', collect($mairie->json('donnees'))->pluck('question')->all());

        // Un droit expiré disparaît
        AccessRule::query()->update(['expire_le' => now()->addDay()]);
        $this->travel(2)->days();
        $this->connecte('agent.commerce')->getJson('/api/droits-disponibles')->assertJsonCount(0, 'donnees');

        // Réservé aux organismes qui posent des questions
        $this->connecte('ousmane')->getJson('/api/droits-disponibles')->assertForbidden();
        $this->connecte('gestionnaire.etat-civil')->getJson('/api/droits-disponibles')->assertForbidden();
    }

    public function test_l_utilisateur_connecte_connait_les_registres_de_son_organisme(): void
    {
        $this->connecte('gestionnaire.etat-civil')->getJson('/api/auth/moi')
            ->assertOk()
            ->assertJsonPath('organisme.registres', ['etat_civil']);

        $this->connecte('agent.commerce')->getJson('/api/auth/moi')
            ->assertJsonPath('organisme.registres', null);
    }

    public function test_le_refus_de_la_source_supprime_la_demande(): void
    {
        $this->connecte('responsable.commerce')->postJson('/api/organisme/droits', [
            'question' => 'vivant', 'finalite' => self::FINALITE, 'justification' => 'Une justification suffisante.',
        ])->assertCreated();
        $id = AccessRule::latest('id')->value('id');

        $this->connecte('gestionnaire.etat-civil')->postJson("/api/registre/acces/{$id}/refuser")->assertOk();

        $this->poser('agent.commerce', 'vivant', ['identifiant' => 'BF-0001'])->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'droit.refuse']);
    }
}
