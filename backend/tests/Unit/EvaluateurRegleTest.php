<?php

namespace Tests\Unit;

use App\Kuma\Assertions\EvaluateurRegle;
use Carbon\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EvaluateurRegleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-17 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_age_gte_a_la_limite_du_jour(): void
    {
        $this->assertTrue(EvaluateurRegle::evaluer('age_gte(date_naissance, 18)', ['date_naissance' => '2008-11-17']));
        $this->assertFalse(EvaluateurRegle::evaluer('age_gte(date_naissance, 18)', ['date_naissance' => '2008-11-18']));
    }

    public function test_champ_absent_donne_impossible_et_non_pas_non(): void
    {
        $this->assertNull(EvaluateurRegle::evaluer('age_gte(date_naissance, 18)', []));
        $this->assertNull(EvaluateurRegle::evaluer('is_empty(condamnations)', ['autre' => 1]));
    }

    public function test_donnee_illisible_donne_impossible(): void
    {
        $this->assertNull(EvaluateurRegle::evaluer('age_gte(date_naissance, 18)', ['date_naissance' => 'pas une date']));
    }

    public function test_equals_est_exact_apres_normalisation(): void
    {
        $this->assertTrue(EvaluateurRegle::evaluer('equals(nationalite, "BF")', ['nationalite' => 'bf']));
        $this->assertFalse(EvaluateurRegle::evaluer('equals(nationalite, "BF")', ['nationalite' => 'CI']));
        $this->assertTrue(EvaluateurRegle::evaluer('equals(statut, "vivant")', ['statut' => 'Vivant']));
    }

    public function test_is_empty(): void
    {
        $this->assertTrue(EvaluateurRegle::evaluer('is_empty(condamnations)', ['condamnations' => []]));
        $this->assertFalse(EvaluateurRegle::evaluer('is_empty(condamnations)', ['condamnations' => [['x' => 1]]]));
    }

    public function test_in_list(): void
    {
        $this->assertTrue(EvaluateurRegle::evaluer('in_list(pays, "BF|CI|ML")', ['pays' => 'ci']));
        $this->assertFalse(EvaluateurRegle::evaluer('in_list(pays, "BF|CI|ML")', ['pays' => 'FR']));
    }

    public function test_date_gte_aujourd_hui(): void
    {
        $this->assertTrue(EvaluateurRegle::evaluer('date_gte(expire, "today")', ['expire' => '2026-11-17']));
        $this->assertFalse(EvaluateurRegle::evaluer('date_gte(expire, "today")', ['expire' => '2026-11-16']));
    }

    public function test_le_parametre_de_la_question_remplace_le_marqueur(): void
    {
        $donnees = ['date_naissance' => '2005-11-17']; // 21 ans exactement

        $this->assertTrue(EvaluateurRegle::evaluer('age_gte(date_naissance, {parametre})', $donnees, '21'));
        $this->assertFalse(EvaluateurRegle::evaluer('age_gte(date_naissance, {parametre})', $donnees, '22'));
    }

    public function test_aucune_fonction_hors_liste_n_est_executee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EvaluateurRegle::evaluer('system("ls")', ['x' => 1]);
    }

    public function test_une_regle_mal_formee_est_refusee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EvaluateurRegle::evaluer('phpinfo', []);
    }

    public function test_validation_d_une_regle(): void
    {
        $this->assertTrue(EvaluateurRegle::valider('age_gte(date_naissance, 18)'));
        $this->assertTrue(EvaluateurRegle::valider('is_empty(condamnations)'));
        $this->assertFalse(EvaluateurRegle::valider('system("ls")'));
        $this->assertFalse(EvaluateurRegle::valider('age_gte(date_naissance)'));
        $this->assertFalse(EvaluateurRegle::valider('eval(1)'));
    }
}
