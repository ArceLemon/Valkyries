<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UtiliseKuma;
use Tests\TestCase;

/**
 * Après la création d'un organisme, l'administrateur le rend opérationnel :
 * fiche de l'organisme, comptes avec un rôle cohérent, changement de rôle.
 */
class AdministrationComptesTest extends TestCase
{
    use RefreshDatabase, UtiliseKuma;

    protected function setUp(): void
    {
        parent::setUp();
        $this->preparerKuma();
    }

    private function organisme(string $sigle): Organization
    {
        return Organization::where('sigle', $sigle)->firstOrFail();
    }

    public function test_parcours_complet_d_un_nouvel_organisme(): void
    {
        $this->connecte('admin');

        $id = $this->postJson('/api/admin/organisations', [
            'nom' => 'Caisse de sécurité sociale', 'sigle' => 'CNSS', 'roles' => ['demandeuse'],
        ])->assertCreated()->json('id');

        // Fiche : aucun compte, aucun droit, rôles possibles pour ses comptes
        $this->getJson("/api/admin/organisations/{$id}")
            ->assertOk()
            ->assertJsonPath('statut', 'preparation')
            ->assertJsonPath('utilisateurs', [])
            ->assertJsonPath('roles_comptes', ['responsable', 'agent']);

        // L'administrateur crée le responsable de l'organisme
        $this->postJson('/api/admin/utilisateurs', [
            'nom' => 'Responsable CNSS', 'email' => 'resp@cnss.test', 'mot_de_passe' => 'un-mot-de-passe-long',
            'role' => 'responsable', 'organisme_id' => $id,
        ])->assertCreated()->assertJsonPath('organisme', 'CNSS');

        $this->postJson('/api/admin/organisations/'.$id.'/activer')->assertOk();

        // Le responsable peut se connecter et gérer son équipe
        $this->postJson('/api/auth/connexion', ['email' => 'resp@cnss.test', 'mot_de_passe' => 'un-mot-de-passe-long'])
            ->assertOk()
            ->assertJsonPath('utilisateur.role', 'responsable');

        $this->assertDatabaseHas('audit_logs', ['action' => 'compte.cree']);
    }

    public function test_le_role_doit_correspondre_a_l_organisme(): void
    {
        $this->connecte('admin');
        $base = ['nom' => 'X', 'mot_de_passe' => 'un-mot-de-passe-long'];

        // Un gestionnaire dans un organisme qui ne détient pas de registre : refusé
        $this->postJson('/api/admin/utilisateurs', $base + [
            'email' => 'a@x.test', 'role' => 'gestionnaire', 'organisme_id' => $this->organisme('RCCM-DEMO')->id,
        ])->assertStatus(422)->assertJsonValidationErrors('role');

        // Un agent sans organisme : refusé
        $this->postJson('/api/admin/utilisateurs', $base + ['email' => 'b@x.test', 'role' => 'agent'])
            ->assertStatus(422);

        // Un auditeur rattaché à un organisme : refusé
        $this->postJson('/api/admin/utilisateurs', $base + [
            'email' => 'c@x.test', 'role' => 'auditeur', 'organisme_id' => $this->organisme('RCCM-DEMO')->id,
        ])->assertStatus(422);

        // Un citoyen ne se crée pas ici : il s'inscrit lui-même
        $this->postJson('/api/admin/utilisateurs', $base + ['email' => 'd@x.test', 'role' => 'citoyen'])
            ->assertStatus(422);

        // Un gestionnaire dans l'organisme de l'état civil : accepté
        $this->postJson('/api/admin/utilisateurs', $base + [
            'email' => 'e@x.test', 'role' => 'gestionnaire', 'organisme_id' => $this->organisme('DEC-DEMO')->id,
        ])->assertCreated();
    }

    public function test_changer_le_role_ferme_les_sessions(): void
    {
        $agent = $this->utilisateur('agent.commerce');
        $agent->createToken('portail', ['agent']);

        $this->connecte('admin')
            ->patchJson("/api/admin/utilisateurs/{$agent->id}/role", ['role' => 'responsable'])
            ->assertOk()
            ->assertJsonPath('role', 'responsable');

        $this->assertSame(0, $agent->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'compte.role_modifie']);

        // Incohérent avec l'organisme : refusé
        $this->patchJson("/api/admin/utilisateurs/{$agent->id}/role", ['role' => 'gestionnaire'])->assertStatus(422);
        // Le compte d'un citoyen ne change pas de rôle
        $this->patchJson('/api/admin/utilisateurs/'.$this->utilisateur('ousmane')->id.'/role', ['role' => 'auditeur'])
            ->assertStatus(422);
    }

    public function test_revoquer_puis_reactiver(): void
    {
        $agent = $this->utilisateur('agent.commerce');
        $this->connecte('admin');

        $this->postJson("/api/admin/utilisateurs/{$agent->id}/revoquer")->assertOk();
        $this->assertFalse($agent->refresh()->actif);

        $this->postJson("/api/admin/utilisateurs/{$agent->id}/reactiver")->assertOk();
        $this->assertTrue($agent->refresh()->actif);
    }

    public function test_reserve_a_l_administrateur(): void
    {
        $this->connecte('responsable.commerce')->postJson('/api/admin/utilisateurs', [])->assertForbidden();
        $this->connecte('auditeur')->getJson('/api/admin/organisations/1')->assertForbidden();
        $this->assertSame(0, User::where('email', 'like', '%@x.test')->count());
    }
}
