<?php

use App\Http\Controllers\AccesController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ApiOrganismeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\CitoyenController;
use App\Http\Controllers\CleApiController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\DemarcheController;
use App\Http\Controllers\DroitsController;
use App\Http\Controllers\EquipeController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\PreuveController;
use App\Http\Controllers\RegistreController;
use App\Http\Controllers\StatistiquesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API KUMA BANA
|--------------------------------------------------------------------------
*/

// Sans compte
Route::post('/auth/connexion', [AuthController::class, 'connexion']);
// Un citoyen crée son compte (identité vérifiée auprès de l'état civil)
Route::post('/auth/inscription', [AuthController::class, 'inscription'])->middleware('throttle:5,1');
Route::post('/preuves/verifier', [PreuveController::class, 'verifier'])->middleware('throttle:60,1');
Route::get('/preuves/cles', [PreuveController::class, 'cles']);

// Catalogue public : ce qu'une administration peut demander, et à qui
Route::get('/catalogue', [CatalogueController::class, 'index']);
Route::get('/catalogue/{code}', [CatalogueController::class, 'show']);

// Points de contrôle signés du journal : publics (empreinte et signature seulement)
Route::get('/journal/points-de-controle', [JournalController::class, 'pointsDeControle']);

// Logiciel d'une administration, par clé d'API (et signature si l'organisme en a enregistré une)
Route::prefix('v1')->middleware('auth.cle')->group(function () {
    Route::post('/demandes', [ApiOrganismeController::class, 'store']);
    Route::get('/demandes/{reference}', [ApiOrganismeController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'actif'])->group(function () {
    Route::get('/auth/moi', [AuthController::class, 'moi']);
    Route::post('/auth/deconnexion', [AuthController::class, 'deconnexion']);

    // Agent et responsable d'organisme : poser des questions
    Route::middleware('role:agent,responsable')->group(function () {
        Route::post('/demandes', [DemandeController::class, 'store']);
        Route::get('/demandes', [DemandeController::class, 'index']);
        Route::get('/demandes/{reference}', [DemandeController::class, 'show']);
        Route::post('/demandes/{reference}/code', [DemandeController::class, 'code'])->middleware('throttle:20,1');
        Route::get('/droits-disponibles', [DroitsController::class, 'disponibles']);
        Route::get('/demarches', [DemarcheController::class, 'index']);
        Route::post('/demarches/{code}/lancer', [DemarcheController::class, 'lancer']);
    });

    // Indicateurs de pilotage
    Route::get('/statistiques/organisme', [StatistiquesController::class, 'organisme'])
        ->middleware('role:agent,responsable');
    Route::get('/statistiques/plateforme', [StatistiquesController::class, 'plateforme'])
        ->middleware('role:administrateur,auditeur');

    // État des registres : tous les profils d'administration
    Route::get('/registres', [RegistreController::class, 'etat'])
        ->middleware('role:agent,responsable,gestionnaire,administrateur,auditeur');

    // Responsable d'organisme
    Route::middleware('role:responsable')->group(function () {
        Route::get('/equipe', [EquipeController::class, 'index']);
        Route::post('/equipe', [EquipeController::class, 'creer']);
        Route::post('/equipe/{id}/revoquer', [EquipeController::class, 'revoquer']);
        Route::get('/organisme/journal', [JournalController::class, 'organisme']);
        // Clés d'API de l'organisme
        Route::get('/organisme/cles', [CleApiController::class, 'index']);
        Route::post('/organisme/cles', [CleApiController::class, 'creer']);
        Route::post('/organisme/cles/{id}/revoquer', [CleApiController::class, 'revoquer']);
        // Droits de l'organisme et demande d'habilitation
        Route::get('/organisme/droits', [DroitsController::class, 'index']);
        Route::post('/organisme/droits', [DroitsController::class, 'demander']);
    });

    // Gestionnaire de registre : valide les accès à son registre
    Route::middleware('role:gestionnaire')->prefix('registre')->group(function () {
        Route::get('/acces', [AccesController::class, 'index']);
        Route::post('/acces/{id}/valider', [AccesController::class, 'valider']);
        Route::post('/acces/{id}/refuser', [AccesController::class, 'refuser']);
        Route::post('/acces/{id}/retirer', [AccesController::class, 'retirer']);
    });

    // Citoyen : consentements et historique des consultations
    Route::middleware('role:citoyen')->prefix('citoyen')->group(function () {
        Route::get('/demandes-a-valider', [CitoyenController::class, 'aValider']);
        Route::post('/consentements/{id}/accepter', [CitoyenController::class, 'accepter']);
        Route::post('/consentements/{id}/refuser', [CitoyenController::class, 'refuser']);
        Route::post('/consentements/{id}/retirer', [CitoyenController::class, 'retirer']);
        Route::get('/consentements', [CitoyenController::class, 'consentements']);
        Route::get('/historique', [CitoyenController::class, 'historique']);
        Route::get('/preuves', [CitoyenController::class, 'preuves']);
        Route::get('/messages', [CitoyenController::class, 'messages']);
        Route::get('/notifications/abonnement', [CitoyenController::class, 'abonnement']);
    });

    // Journal global : administrateur et auditeur (lecture seule pour l'auditeur)
    Route::middleware('role:administrateur,auditeur')->group(function () {
        Route::get('/journal', [JournalController::class, 'index']);
        Route::post('/journal/verifier-chaine', [JournalController::class, 'verifier']);
    });
    Route::post('/journal/points-de-controle', [JournalController::class, 'signerPointDeControle'])
        ->middleware('role:administrateur');

    // Administrateur de la plateforme
    Route::middleware('role:administrateur')->prefix('admin')->group(function () {
        Route::get('/organisations', [AdminController::class, 'organisations']);
        Route::post('/organisations', [AdminController::class, 'creerOrganisation']);
        Route::get('/organisations/{id}', [AdminController::class, 'organisation'])->whereNumber('id');
        Route::post('/organisations/{id}/activer', [AdminController::class, 'activer']);
        Route::post('/organisations/{id}/suspendre', [AdminController::class, 'suspendre']);
        Route::post('/droits', [AdminController::class, 'creerDroit']);
        Route::get('/catalogue', [AdminController::class, 'catalogue']);
        Route::patch('/catalogue/{id}', [AdminController::class, 'modifierAssertion']);
        Route::get('/utilisateurs', [AdminController::class, 'utilisateurs']);
        Route::post('/utilisateurs', [AdminController::class, 'creerUtilisateur']);
        Route::patch('/utilisateurs/{id}/role', [AdminController::class, 'modifierRole']);
        Route::post('/utilisateurs/{id}/revoquer', [AdminController::class, 'revoquer']);
        Route::post('/utilisateurs/{id}/reactiver', [AdminController::class, 'reactiver']);
    });
});
