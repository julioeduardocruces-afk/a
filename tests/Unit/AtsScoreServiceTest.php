<?php

namespace Tests\Unit;

use App\Services\AtsScoreService;
use PHPUnit\Framework\TestCase;

class AtsScoreServiceTest extends TestCase
{
    private AtsScoreService $scorer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new AtsScoreService();
    }

    public function test_score_returns_all_components(): void
    {
        $text = "RESUMEN PROFESIONAL\nDesarrollador Full Stack con experiencia.\n\nEXPERIENCIA LABORAL\nEmpresa X - Desarrollador (2020-2024)\n\nEDUCACION\nUniversidad Y - Ingenieria\n\nHABILIDADES\nPHP, Laravel, JavaScript";

        $score = $this->scorer->score($text, 'Desarrollador Full Stack', 'TI', ['PHP', 'Laravel', 'JavaScript']);

        $this->assertArrayHasKey('overall', $score);
        $this->assertArrayHasKey('headline_match', $score);
        $this->assertArrayHasKey('keyword_density', $score);
        $this->assertArrayHasKey('sections_present', $score);
        $this->assertArrayHasKey('length', $score);
        $this->assertArrayHasKey('structure', $score);

        $this->assertGreaterThanOrEqual(0, $score['overall']);
        $this->assertLessThanOrEqual(100, $score['overall']);
    }

    public function test_headline_match_scores_high_when_role_in_header(): void
    {
        $text = "Juan Perez\nDesarrollador Full Stack\n\nRESUMEN\nExperiencia en desarrollo web.";

        $score = $this->scorer->score($text, 'Desarrollador Full Stack', 'TI');
        $this->assertGreaterThanOrEqual(80, $score['headline_match']);
    }

    public function test_keyword_density_scores_high_with_matching_keywords(): void
    {
        $text = "PHP Laravel JavaScript React Node.js PostgreSQL Docker Kubernetes AWS CI/CD";

        $score = $this->scorer->score($text, 'Dev', 'TI', ['PHP', 'Laravel', 'JavaScript', 'React', 'Docker']);
        $this->assertGreaterThanOrEqual(80, $score['keyword_density']);
    }

    public function test_sections_detect_standard_sections(): void
    {
        $text = "RESUMEN\nAlgo\n\nEXPERIENCIA\nAlgo\n\nEDUCACION\nAlgo\n\nHABILIDADES\nAlgo\n\nCERTIFICACIONES\nAlgo";

        $score = $this->scorer->score($text, 'Test', 'Test');
        $this->assertGreaterThanOrEqual(80, $score['sections_present']);
    }

    public function test_length_penalizes_very_short_cv(): void
    {
        $text = "Hola mundo corto";

        $score = $this->scorer->score($text, 'Test', 'Test');
        $this->assertLessThanOrEqual(60, $score['length']);
    }

    public function test_structure_penalizes_table_patterns(): void
    {
        $text = "| Col1 | Col2 | Col3 |\n| a | b | c |\n| d | e | f |\n| g | h | i |";

        $score = $this->scorer->score($text, 'Test', 'Test');
        $this->assertLessThanOrEqual(80, $score['structure']);
    }
}
