<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Épreuve « ne transmettre que l'utile » : la réponse est un oui ou un non,
 * la donnée brute n'est conservée nulle part.
 */
class DemandesTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    public function test_majeur_oui_sans_aucune_donnee_brute_conservee(): void
    {
        $reponse = $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001']);

        $reponse->assertCreated()
            ->assertJsonPath('statut', 'terminee')
            ->assertJsonPath('reponse', 'oui');
        $this->assertNotNull($reponse->json('attestation'));

        // Rien de la fiche ne sort vers l'agent
        $this->assertStringNotContainsString('1996-03-14', $reponse->getContent());
        $this->assertStringNotContainsString('ILBOUDO', $reponse->getContent());

        // Ni en base, ni dans le journal
        $demande = \App\Models\VerificationRequest::firstOrFail();
        $this->assertNull($demande->getRawOriginal('identifiant_enc'));
        $this->assertNull($demande->getRawOriginal('date_naissance_enc'));

        $tout = json_encode(DB::table('audit_logs')->get())
            .json_encode(DB::table('verification_requests')->get())
            .json_encode(DB::table('attestations')->get());
        foreach (['1996-03-14', 'BF-0001', 'ILBOUDO', 'Ouagadougou'] as $secret) {
            $this->assertStringNotContainsString($secret, $tout, "La donnée « {$secret} » ne doit être stockée nulle part.");
        }
    }

    public function test_mineur_donne_non_et_aucune_attestation(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0002'])
            ->assertCreated()
            ->assertJsonPath('reponse', 'non')
            ->assertJsonPath('attestation', null);
    }

    public function test_nationalite_burkinabe_oui_et_etrangere_non(): void
    {
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-0001'])
            ->assertJsonPath('reponse', 'oui');
        $this->poser('agent.commerce', 'nationalite_burkinabe', ['identifiant' => 'BF-0004'])
            ->assertJsonPath('reponse', 'non');
    }

    public function test_identifiant_obligatoire_et_seul_retenu(): void
    {
        // Sans identifiant : refus. Nom ou date de naissance ne remplacent pas l'identifiant.
        $this->poser('agent.commerce', 'majeur', ['nom' => 'ILBOUDO'])->assertStatus(422);
        $this->assertSame(0, \App\Models\VerificationRequest::count());
    }

    public function test_citoyen_introuvable_n_est_pas_un_non(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-9999'])
            ->assertCreated()
            ->assertJsonPath('reponse', 'impossible')
            ->assertJsonPath('motif', 'introuvable');
    }

    public function test_droit_absent_refuse_et_journalise(): void
    {
        // La mairie n'a pas le droit de demander le casier
        $this->poser('agent.mairie', 'casier_vierge', ['identifiant' => 'BF-0001'], 'Délivrance d\'un document')
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'demande.refusee', 'resultat' => 'refuse']);
        $this->assertSame(0, \App\Models\VerificationRequest::count());
    }

    public function test_droit_non_valide_par_la_source_est_sans_effet(): void
    {
        // Droit demandé par la mairie mais pas encore validé par le gestionnaire du registre
        $this->poser('agent.mairie', 'certificat_nationalite_valide', ['identifiant' => 'BF-0001'], 'Délivrance d\'un document')
            ->assertForbidden();
    }

    public function test_mauvaise_finalite_est_refusee(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'], 'Autre finalité')
            ->assertForbidden();
    }

    public function test_question_parametree(): void
    {
        // Le registre du commerce n'a pas le droit age_minimum : refusé
        $this->poser('agent.commerce', 'age_minimum', ['identifiant' => 'BF-0001'], 'Immatriculation d\'une entreprise', '21')
            ->assertForbidden();
    }

    public function test_quota_de_demandes(): void
    {
        config(['kuma.quota_demandes_par_minute' => 2]);

        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertStatus(429);
    }

    public function test_demarche_creation_entreprise_et_pieces_evitees(): void
    {
        $this->connecte('agent.commerce');

        $reponse = $this->postJson('/api/demarches/creation-entreprise/lancer', [
            'citoyen' => ['identifiant' => 'BF-0001'],
        ]);

        $reponse->assertCreated();
        $this->assertCount(3, $reponse->json('demandes'));
        // majeur et nationalité répondent tout de suite ; le casier attend le consentement
        $this->assertSame(2, $reponse->json('pieces_evitees'));
        $statuts = collect($reponse->json('demandes'))->pluck('statut', 'question');
        $this->assertSame('en_attente_consentement', $statuts['casier_vierge']);
    }

    public function test_un_agent_ne_voit_pas_les_demandes_d_un_autre_organisme(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $this->connecte('agent.mairie')->getJson('/api/demandes')
            ->assertOk()
            ->assertJsonCount(0, 'donnees');
    }

    public function test_les_lignes_du_journal_sont_attribuees_a_l_organisme(): void
    {
        $this->poser('agent.commerce', 'majeur', ['identifiant' => 'BF-0001'])->assertCreated();

        $consultation = AuditLog::where('action', 'donnee.consultee')->firstOrFail();
        $this->assertSame('oui', $consultation->details['reponse']);
        $this->assertNotNull($consultation->organization_id);
        $this->assertNotNull($consultation->sujet_pseudo);
    }
}
