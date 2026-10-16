<?php

namespace InformaticadosLago\GeneradorPlantillas\Tests\Feature;

use InformaticadosLago\GeneradorPlantillas\GeneradorPlantillas;
use InformaticadosLago\GeneradorPlantillas\Tests\Modelos\Empresa;
use InformaticadosLago\GeneradorPlantillas\Tests\TestCase;

class BladeTest extends TestCase
{
    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->carpeta = sys_get_temp_dir() . '/generador-plantillas-' . uniqid();
        config()->set('generador-plantillas.blade', $this->carpeta);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->carpeta . '/*') ?: [] as $fichero) {
            unlink($fichero);
        }
        if (is_dir($this->carpeta)) {
            rmdir($this->carpeta);
        }

        parent::tearDown();
    }

    /** Guarda la plantilla por la API, como el editor, y pinta su Blade con esos datos. */
    private function pintar(string $contenido, array $datos, string $nombre = 'recibo'): string
    {
        $this->putJson($this->url($nombre), ['xml' => $this->xml($nombre, $contenido), 'version' => 0])->assertCreated();

        return view(GeneradorPlantillas::vista($nombre, null), ['datos' => $datos])->render();
    }

    public function test_guardar_escribe_la_blade_del_dueno_y_borrar_la_borra(): void
    {
        $this->como(Empresa::conId(5));

        $this->putJson($this->url('Recibo Alquiler'), ['xml' => $this->xml('Recibo Alquiler'), 'version' => 0])->assertCreated();
        $this->assertFileExists($this->carpeta . '/recibo-alquiler-5.blade.php');
        $this->assertSame('generador-plantillas::recibo-alquiler-5', GeneradorPlantillas::vista('Recibo Alquiler', Empresa::conId(5)));
        $this->assertTrue(view()->exists(GeneradorPlantillas::vista('Recibo Alquiler', Empresa::conId(5))));

        // Guardar otra vez la pisa.
        $this->putJson($this->url('Recibo Alquiler'), ['xml' => $this->xml('Recibo Alquiler', '<cabecera altura="20"><texto x="10" y="5" ancho="50" alto="5">Nuevo</texto></cabecera>'), 'version' => 1])
            ->assertOk();
        $this->assertStringContainsString('Nuevo', file_get_contents($this->carpeta . '/recibo-alquiler-5.blade.php'));

        // Duplicar escribe la de la copia.
        $this->postJson($this->url('Recibo Alquiler', 'duplicar'), ['nombre' => 'otro recibo'])->assertCreated();
        $this->assertFileExists($this->carpeta . '/otro-recibo-5.blade.php');

        $this->deleteJson($this->url('Recibo Alquiler'))->assertOk();
        $this->assertFileDoesNotExist($this->carpeta . '/recibo-alquiler-5.blade.php');
        $this->assertFileExists($this->carpeta . '/otro-recibo-5.blade.php');
    }

    public function test_sin_carpeta_no_se_escribe_nada(): void
    {
        config()->set('generador-plantillas.blade', null);

        $this->putJson($this->url('recibo'), ['xml' => $this->xml('recibo'), 'version' => 0])->assertCreated();

        $this->assertDirectoryDoesNotExist($this->carpeta);
        $this->assertNull(GeneradorPlantillas::vista('recibo', null));
    }

    public function test_pinta_textos_tablas_y_cuerpo_con_sus_formatos(): void
    {
        $html = $this->pintar(
            '<cabecera altura="60">'
            . '<texto x="10" y="10" ancho="80" alto="10">Nº {{recibo.numero}}' . "\n" . 'Total: {{recibo.total|moneda}} el {{ recibo.fecha | fecha }}</texto>'
            . '<tabla x="10" y="30" ancho="100" datos="inquilinos"><columna campo="nombre" titulo="Inquilino" ancho="100"/></tabla>'
            . '<tabla x="10" y="40" ancho="100" datos="vacia"><columna campo="nombre" titulo="No sale" ancho="100"/></tabla>'
            . '<tabla x="10" y="50" ancho="100" datos=""><columna campo="recibo.total" titulo="Sin lista" ancho="100" formato="moneda"/></tabla>'
            . '</cabecera>'
            . '<cuerpo datos="lineas">'
            . '<columna campo="concepto" titulo="Concepto" ancho="70"/>'
            . '<columna campo="importe" titulo="Importe" ancho="30" alinear="derecha" formato="moneda"/>'
            . '</cuerpo>',
            [
                'recibo'     => ['numero' => 'A-7', 'total' => 1234.5, 'fecha' => '2026-10-01'],
                'inquilinos' => [['nombre' => 'Ana'], ['nombre' => 'Luis']],
                'vacia'      => [],
                'lineas'     => [['concepto' => 'Renta octubre', 'importe' => 700], ['concepto' => 'Agua', 'importe' => 34.5]],
            ],
        );

        $this->assertStringContainsString('Nº A-7<br>', $html);
        $this->assertStringContainsString('Total: 1.234,50 € el 01/10/2026', $html);
        $this->assertStringContainsString('>Ana</td>', $html);
        $this->assertStringContainsString('>Luis</td>', $html);
        $this->assertStringNotContainsString('No sale', $html);
        $this->assertStringContainsString('Sin lista', $html);
        $this->assertStringContainsString('>Renta octubre</td>', $html);
        $this->assertStringContainsString('>700,00 €</td>', $html);
        $this->assertStringContainsString('>34,50 €</td>', $html);
        $this->assertStringContainsString('@page { size: 210mm 297mm;', $html);
    }

    public function test_un_texto_con_codigo_no_se_guarda(): void
    {
        $peligros = [
            '{{ 7*7 }}', '{{ $x }}', "{!! 'x' !!}", '{{-- nada --}}', '@php echo 1; @endphp', '@if(true)',
            "Total\n@php", '{{recibo.total}}@php', '@{{recibo.total}}', "<?php echo 'x'; ?>", 'éé@php',
        ];

        foreach ($peligros as $i => $texto) {
            $xml = $this->xml("p{$i}", '<cabecera altura="20"><texto x="10" y="5" ancho="80" alto="10">' . htmlspecialchars($texto, ENT_XML1) . '</texto></cabecera>');
            $this->putJson($this->url("p{$i}"), ['xml' => $xml, 'version' => 0])
                ->assertStatus(422)
                ->assertJsonPath('error', fn ($error) => str_contains($error, 'parece código'));
        }

        // También en el título de una columna.
        $xml = $this->xml('columna', '<cuerpo datos="lineas"><columna campo="a" titulo="@include(\'x\')" ancho="100"/></cuerpo>');
        $this->putJson($this->url('columna'), ['xml' => $xml, 'version' => 0])->assertStatus(422);

        $this->assertSame([], glob($this->carpeta . '/*') ?: []);
    }

    public function test_los_textos_normales_van_tal_cual(): void
    {
        $html = $this->pintar(
            '<cabecera altura="30">'
            . '<texto x="10" y="10" ancho="80" alto="10">' . htmlspecialchars('info@empresa.com · { llaves } · a < b & c · 50 %', ENT_XML1) . '</texto>'
            . '</cabecera>'
            . '<cuerpo datos="lineas"><columna campo="x\'); echo(\'PELIGRO" titulo="Rara" ancho="100"/></cuerpo>',
            ['lineas' => [['x' => 'fila']]],
        );

        $this->assertStringContainsString('info@empresa.com · { llaves } · a &lt; b &amp; c · 50 %', $html);
        // Un campo que no es grupo.campo no sale (ni se cuela en el código).
        $this->assertStringNotContainsString('PELIGRO', $html);
        $this->assertStringContainsString('info@empresa.com · { llaves }', file_get_contents($this->carpeta . '/recibo.blade.php'));
    }

    public function test_los_datos_pueden_traer_html_y_no_se_cuela(): void
    {
        $html = $this->pintar(
            '<cabecera altura="20"><texto x="10" y="5" ancho="80" alto="10">{{cliente.nombre}}</texto></cabecera>'
            . '<cuerpo datos="lineas"><columna campo="concepto" titulo="Concepto" ancho="100"/></cuerpo>',
            ['cliente' => ['nombre' => "<b>Ana</b>\nSegunda"], 'lineas' => [['concepto' => '<i>x</i>']]],
        );

        $this->assertStringContainsString('&lt;b&gt;Ana&lt;/b&gt;<br>' . "\n" . 'Segunda', $html);
        $this->assertStringContainsString('&lt;i&gt;x&lt;/i&gt;', $html);
    }

    public function test_la_marca_de_agua_del_programa_solo_sale_si_la_pasa(): void
    {
        $sin = $this->pintar('<cabecera altura="20"/>', []);
        $this->assertStringNotContainsString('rotate(315deg)', $sin);

        $con = view(GeneradorPlantillas::vista('recibo', null), ['datos' => [], 'marcaAgua' => 'EN PREPARACIÓN <b>'])->render();
        $this->assertStringContainsString('rotate(315deg)', $con);
        $this->assertStringContainsString('EN PREPARACIÓN &lt;b&gt;', $con);
    }
}
