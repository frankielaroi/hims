<?php

namespace Tests\Feature;

use App\Models\Drug;
use App\Models\Patient;
use App\Models\PatientAllergy;
use App\Services\AllergyEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AllergyEngineTest extends TestCase
{
    use RefreshDatabase;

    protected AllergyEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(AllergyEngine::class);
    }

    public function test_matches_an_allergen_against_the_brand_name_as_an_exact_match(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Augmentin')->create();
        $drug = Drug::factory()->named('Augmentin 625mg', 'Amoxicillin-Clavulanate')->create();

        $conflict = $this->engine->checkDrug($patient, $drug);

        $this->assertNotNull($conflict);
        $this->assertSame('exact', $conflict['match_type']);
        $this->assertSame('Augmentin', $conflict['allergen']);
    }

    public function test_matches_an_allergen_against_the_generic_name(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Ibuprofen')->create();
        $drug = Drug::factory()->named('Brufen 400mg', 'Ibuprofen')->create();

        $conflict = $this->engine->checkDrug($patient, $drug);

        $this->assertNotNull($conflict);
        $this->assertSame('generic', $conflict['match_type']);
    }

    public function test_matches_a_class_allergen_against_a_drug_in_that_class(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Penicillin')->create();
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();

        $conflict = $this->engine->checkDrug($patient, $drug);

        $this->assertNotNull($conflict);
        $this->assertSame('class', $conflict['match_type']);
    }

    public function test_matches_a_class_allergen_using_the_recorded_drug_class(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Sulfa')->create();
        $drug = Drug::factory()->named('Fansidar', 'Pyrimethamine', 'sulfonamide')->create();

        $conflict = $this->engine->checkDrug($patient, $drug);

        $this->assertNotNull($conflict);
        $this->assertSame('class', $conflict['match_type']);
    }

    public function test_does_not_match_an_unrelated_drug(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Penicillin')->create();
        $drug = Drug::factory()->named('Panadol 500mg', 'Paracetamol')->create();

        $this->assertNull($this->engine->checkDrug($patient, $drug));
    }

    public function test_does_not_match_on_a_partial_word(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Amox')->create();
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();

        $this->assertNull($this->engine->checkDrug($patient, $drug));
    }

    public function test_ignores_an_allergy_that_is_no_longer_active(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Ibuprofen')->resolved()->create();
        $drug = Drug::factory()->named('Brufen 400mg', 'Ibuprofen')->create();

        $this->assertNull($this->engine->checkDrug($patient, $drug));
    }

    public function test_reports_the_most_serious_allergy_when_several_match(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Penicillin')->create();
        PatientAllergy::factory()->for($patient)->to('Amoxicillin')->lifeThreatening()->create();
        $drug = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();

        $conflict = $this->engine->checkDrug($patient, $drug);

        $this->assertSame('life-threatening', $conflict['severity']);
        $this->assertTrue($conflict['is_critical']);
    }

    public function test_returns_one_conflict_per_conflicting_drug(): void
    {
        $patient = Patient::factory()->create();
        PatientAllergy::factory()->for($patient)->to('Penicillin')->create();
        $conflicting = Drug::factory()->named('Amoxil 500mg', 'Amoxicillin')->create();
        $safe = Drug::factory()->named('Panadol 500mg', 'Paracetamol')->create();

        $conflicts = $this->engine->check($patient, [$conflicting, $safe]);

        $this->assertCount(1, $conflicts);
        $this->assertSame($conflicting->id, $conflicts[0]['drug_id']);
    }

    #[DataProvider('criticalSeverityProvider')]
    public function test_identifies_which_severities_require_a_justification(string $severity, bool $expected): void
    {
        $this->assertSame($expected, $this->engine->isCritical($severity));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function criticalSeverityProvider(): array
    {
        return [
            'life-threatening blocks' => ['life-threatening', true],
            'severe blocks' => ['severe', true],
            'moderate warns only' => ['moderate', false],
            'mild warns only' => ['mild', false],
            'unknown warns only' => ['unknown', false],
        ];
    }

    public function test_rejects_a_justification_that_is_too_short_to_be_meaningful(): void
    {
        $this->assertFalse($this->engine->isJustified('too short'));
        $this->assertFalse($this->engine->isJustified(null));
        $this->assertTrue($this->engine->isJustified('Prior tolerance documented; benefit outweighs risk.'));
    }
}
