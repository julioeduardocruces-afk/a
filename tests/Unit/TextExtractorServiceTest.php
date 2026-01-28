<?php

namespace Tests\Unit;

use App\Services\TextExtractorService;
use PHPUnit\Framework\TestCase;

class TextExtractorServiceTest extends TestCase
{
    private TextExtractorService $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new TextExtractorService();
    }

    public function test_structure_text_detects_experience_section(): void
    {
        $text = "Juan Perez\njuan@mail.com\n\nExperiencia Laboral\nEmpresa ABC - Desarrollador (2020-2024)\n- Desarrollo de aplicaciones web\n\nEducacion\nUniversidad XYZ - Ingenieria (2016-2020)";

        $result = $this->extractor->structureText($text);

        $this->assertArrayHasKey('header', $result);
        $this->assertArrayHasKey('experience', $result);
        $this->assertArrayHasKey('education', $result);
        $this->assertNotEmpty($result['experience']);
    }

    public function test_structure_text_detects_skills_section(): void
    {
        $text = "Nombre\n\nHabilidades\nPHP, Laravel, JavaScript, React\n\nIdiomas\nEspanol, Ingles";

        $result = $this->extractor->structureText($text);

        $this->assertArrayHasKey('skills', $result);
        $this->assertNotEmpty($result['skills']);
        $this->assertArrayHasKey('languages', $result);
    }

    public function test_structure_text_handles_empty_input(): void
    {
        $result = $this->extractor->structureText('');

        $this->assertArrayHasKey('header', $result);
        $this->assertArrayHasKey('experience', $result);
    }

    public function test_structure_text_detects_summary(): void
    {
        $text = "Resumen Profesional\nDesarrollador con 5 anos de experiencia en PHP y Laravel.\n\nExperiencia\nAlgo";

        $result = $this->extractor->structureText($text);

        $this->assertNotEmpty($result['summary']);
    }
}
