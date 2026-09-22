<?php

namespace Database\Seeders;

use App\Models\Icd10Code;
use Illuminate\Database\Seeder;

/**
 * A working subset of ICD-10 for a Ghanaian district hospital, grouped by the
 * chapter headings clinicians actually navigate by.
 *
 * This is a curated starting set covering common presentations, not the complete
 * WHO classification. A facility doing statutory reporting or NHIS claims coding
 * should load the official ICD-10 release (or its DHIMS2 code list) on top of
 * this; the `code` column is unique, so re-seeding a fuller list is additive.
 */
class Icd10CodesSeeder extends Seeder
{
    /**
     * @var array<string, list<array{0: string, 1: string}>>
     */
    protected const CODES = [
        'Infectious & parasitic' => [
            ['A00', 'Cholera'],
            ['A01.0', 'Typhoid fever'],
            ['A06', 'Amoebiasis'],
            ['A09', 'Infectious gastroenteritis and colitis, unspecified'],
            ['A15', 'Respiratory tuberculosis, bacteriologically and histologically confirmed'],
            ['A16', 'Respiratory tuberculosis, not confirmed bacteriologically or histologically'],
            ['A41.9', 'Sepsis, unspecified organism'],
            ['A90', 'Dengue fever'],
            ['B01', 'Varicella (chickenpox)'],
            ['B05', 'Measles'],
            ['B15', 'Acute hepatitis A'],
            ['B16', 'Acute hepatitis B'],
            ['B20', 'HIV disease resulting in infectious and parasitic diseases'],
            ['B24', 'Unspecified HIV disease'],
            ['B35', 'Dermatophytosis'],
            ['B37', 'Candidiasis'],
            ['B50', 'Plasmodium falciparum malaria'],
            ['B51', 'Plasmodium vivax malaria'],
            ['B54', 'Unspecified malaria'],
            ['B76', 'Hookworm disease'],
            ['B77', 'Ascariasis'],
            ['B82', 'Unspecified intestinal parasitism'],
        ],
        'Neoplasms & blood' => [
            ['C50', 'Malignant neoplasm of breast'],
            ['C53', 'Malignant neoplasm of cervix uteri'],
            ['C61', 'Malignant neoplasm of prostate'],
            ['D50', 'Iron deficiency anaemia'],
            ['D57', 'Sickle-cell disorders'],
            ['D64.9', 'Anaemia, unspecified'],
        ],
        'Endocrine, nutritional & metabolic' => [
            ['E03.9', 'Hypothyroidism, unspecified'],
            ['E05.9', 'Thyrotoxicosis, unspecified'],
            ['E10', 'Type 1 diabetes mellitus'],
            ['E11', 'Type 2 diabetes mellitus'],
            ['E14', 'Unspecified diabetes mellitus'],
            ['E43', 'Unspecified severe protein-energy malnutrition'],
            ['E44', 'Protein-energy malnutrition of moderate and mild degree'],
            ['E46', 'Unspecified protein-energy malnutrition'],
            ['E66', 'Obesity'],
            ['E86', 'Volume depletion'],
            ['E87.6', 'Hypokalaemia'],
        ],
        'Mental & behavioural' => [
            ['F10', 'Mental and behavioural disorders due to use of alcohol'],
            ['F20', 'Schizophrenia'],
            ['F31', 'Bipolar affective disorder'],
            ['F32', 'Depressive episode'],
            ['F41', 'Other anxiety disorders'],
        ],
        'Nervous system' => [
            ['G40', 'Epilepsy'],
            ['G43', 'Migraine'],
        ],
        'Eye & ear' => [
            ['H10', 'Conjunctivitis'],
            ['H25', 'Senile cataract'],
            ['H40', 'Glaucoma'],
            ['H60', 'Otitis externa'],
            ['H66', 'Suppurative and unspecified otitis media'],
        ],
        'Circulatory' => [
            ['I10', 'Essential (primary) hypertension'],
            ['I11', 'Hypertensive heart disease'],
            ['I20', 'Angina pectoris'],
            ['I21', 'Acute myocardial infarction'],
            ['I50', 'Heart failure'],
            ['I63', 'Cerebral infarction'],
            ['I64', 'Stroke, not specified as haemorrhage or infarction'],
            ['I83', 'Varicose veins of lower extremities'],
        ],
        'Respiratory' => [
            ['J00', 'Acute nasopharyngitis (common cold)'],
            ['J02', 'Acute pharyngitis'],
            ['J03', 'Acute tonsillitis'],
            ['J06.9', 'Acute upper respiratory infection, unspecified'],
            ['J11', 'Influenza, virus not identified'],
            ['J15', 'Bacterial pneumonia, not elsewhere classified'],
            ['J18', 'Pneumonia, unspecified organism'],
            ['J20', 'Acute bronchitis'],
            ['J44', 'Chronic obstructive pulmonary disease'],
            ['J45', 'Asthma'],
        ],
        'Digestive' => [
            ['K02', 'Dental caries'],
            ['K21', 'Gastro-oesophageal reflux disease'],
            ['K25', 'Gastric ulcer'],
            ['K29.7', 'Gastritis, unspecified'],
            ['K30', 'Functional dyspepsia'],
            ['K35', 'Acute appendicitis'],
            ['K40', 'Inguinal hernia'],
            ['K52.9', 'Noninfective gastroenteritis and colitis, unspecified'],
            ['K59.0', 'Constipation'],
            ['K64', 'Haemorrhoids'],
            ['K80', 'Cholelithiasis'],
        ],
        'Skin' => [
            ['L01', 'Impetigo'],
            ['L03', 'Cellulitis'],
            ['L20', 'Atopic dermatitis'],
            ['L23', 'Allergic contact dermatitis'],
            ['L30.9', 'Dermatitis, unspecified'],
            ['L50', 'Urticaria'],
        ],
        'Musculoskeletal' => [
            ['M06', 'Other rheumatoid arthritis'],
            ['M10', 'Gout'],
            ['M17', 'Gonarthrosis (osteoarthritis of knee)'],
            ['M54.5', 'Low back pain'],
            ['M79.1', 'Myalgia'],
        ],
        'Genitourinary' => [
            ['N10', 'Acute tubulo-interstitial nephritis'],
            ['N18', 'Chronic kidney disease'],
            ['N20', 'Calculus of kidney and ureter'],
            ['N39.0', 'Urinary tract infection, site not specified'],
            ['N40', 'Benign prostatic hyperplasia'],
            ['N73.9', 'Female pelvic inflammatory disease, unspecified'],
            ['N76', 'Other inflammation of vagina and vulva'],
            ['N92', 'Excessive, frequent and irregular menstruation'],
        ],
        'Pregnancy & childbirth' => [
            ['O03', 'Spontaneous abortion'],
            ['O14', 'Pre-eclampsia'],
            ['O15', 'Eclampsia'],
            ['O21', 'Excessive vomiting in pregnancy'],
            ['O23', 'Infections of genitourinary tract in pregnancy'],
            ['O24', 'Diabetes mellitus in pregnancy, childbirth and the puerperium'],
            ['O80', 'Single spontaneous delivery'],
            ['O99.0', 'Anaemia complicating pregnancy, childbirth and the puerperium'],
        ],
        'Perinatal' => [
            ['P07', 'Disorders related to short gestation and low birth weight'],
            ['P39.9', 'Infection specific to the perinatal period, unspecified'],
            ['P59', 'Neonatal jaundice from other and unspecified causes'],
        ],
        'Symptoms & signs' => [
            ['R05', 'Cough'],
            ['R10.4', 'Other and unspecified abdominal pain'],
            ['R11', 'Nausea and vomiting'],
            ['R42', 'Dizziness and giddiness'],
            ['R50.9', 'Fever, unspecified'],
            ['R51', 'Headache'],
            ['R53', 'Malaise and fatigue'],
            ['R56.0', 'Febrile convulsions'],
        ],
        'Injury & poisoning' => [
            ['S01', 'Open wound of head'],
            ['S52', 'Fracture of forearm'],
            ['S72', 'Fracture of femur'],
            ['S81', 'Open wound of lower leg'],
            ['T14.1', 'Open wound of unspecified body region'],
            ['T30', 'Burn and corrosion, body region unspecified'],
            ['T63', 'Toxic effect of contact with venomous animals'],
            ['T78.2', 'Anaphylactic shock, unspecified'],
        ],
        'Encounters & screening' => [
            ['Z00', 'General examination without complaint or reported diagnosis'],
            ['Z23', 'Encounter for immunization'],
            ['Z33', 'Pregnant state, incidental'],
            ['Z34', 'Supervision of normal pregnancy'],
            ['Z39', 'Postpartum care and examination'],
        ],
    ];

    public function run(): void
    {
        foreach (self::CODES as $category => $codes) {
            foreach ($codes as [$code, $name]) {
                Icd10Code::updateOrCreate(
                    ['code' => $code],
                    ['name' => $name, 'category' => $category]
                );
            }
        }
    }
}
