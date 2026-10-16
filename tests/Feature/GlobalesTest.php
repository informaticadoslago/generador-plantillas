<?php

namespace InformaticadosLago\GeneradorPlantillas\Tests\Feature;

use InformaticadosLago\GeneradorPlantillas\GeneradorPlantillas;
use InformaticadosLago\GeneradorPlantillas\Models\Plantilla;
use InformaticadosLago\GeneradorPlantillas\Tests\Modelos\Empresa;
use InformaticadosLago\GeneradorPlantillas\Tests\TestCase;

class GlobalesTest extends TestCase
{
    private string $carpeta;

    private string $carpetaBlade;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->carpeta      = sys_get_temp_dir() . '/generador-plantillas-globales-' . uniqid();
        $this->carpetaBlade = sys_get_temp_dir() . '/generador-plantillas-blade-' . uniqid();
        $app['config']->set('generador-plantillas.blade', $this->carpetaBlade);
        $app['config']->set('generador-plantillas.globales', [
            'carpeta'    => $this->carpeta,
            'prefijo'    => 'generador-plantillas/globales',
            'middleware' => ['web'],
            'volver'     => 'maestros/plantillas',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([$this->carpeta, $this->carpetaBlade] as $carpeta) {
            foreach (glob($carpeta . '/*') ?: [] as $fichero) {
                unlink($fichero);
            }
            if (is_dir($carpeta)) {
                rmdir($carpeta);
            }
        }

        parent::tearDown();
    }

    private function urlGlobal(string $nombre = '', string $accion = ''): string
    {
        return str_replace('/generador-plantillas/', '/generador-plantillas/globales/', $this->url($nombre, $accion));
    }

    public function test_las_globales_se_guardan_como_ficheros_sin_blade(): void
    {
        $this->putJson($this->urlGlobal('Recibo Alquiler'), ['xml' => $this->xml('Recibo Alquiler'), 'version' => 0])
            ->assertCreated()
            ->assertJson(['nombre' => 'Recibo Alquiler', 'version' => 1]);

        $fichero = $this->carpeta . '/recibo-alquiler.xml';
        $this->assertFileExists($fichero);
        $this->assertStringContainsString('revision="1"', file_get_contents($fichero));
        $this->assertSame(0, Plantilla::count());
        $this->assertDirectoryDoesNotExist($this->carpetaBlade);

        // Guardar otra vez sube la versión; con la vieja, 409.
        $this->putJson($this->urlGlobal('recibo alquiler'), ['xml' => $this->xml('recibo alquiler', '<cabecera altura="30"/>'), 'version' => 1])
            ->assertOk()
            ->assertJson(['version' => 2]);
        $this->putJson($this->urlGlobal('Recibo Alquiler'), ['xml' => $this->xml('Recibo Alquiler'), 'version' => 1])
            ->assertStatus(409)
            ->assertJson(['codigo' => 'version', 'version_actual' => 2]);

        $this->getJson($this->urlGlobal())->assertOk()
            ->assertJsonCount(1, 'plantillas')
            ->assertJsonPath('plantillas.0.nombre', 'recibo alquiler')
            ->assertJsonPath('plantillas.0.version', 2);
        $this->assertStringContainsString('<cabecera altura="30"/>', $this->getJson($this->urlGlobal('RECIBO ALQUILER'))->assertOk()->json('xml'));

        $this->postJson($this->urlGlobal('recibo alquiler', 'duplicar'), ['nombre' => 'factura'])->assertCreated();
        $this->assertFileExists($this->carpeta . '/factura.xml');

        $this->deleteJson($this->urlGlobal('recibo alquiler'))->assertOk();
        $this->assertFileDoesNotExist($fichero);
        $this->getJson($this->urlGlobal('recibo alquiler'))->assertNotFound();
    }

    public function test_el_editor_de_las_globales_usa_su_api_y_su_volver(): void
    {
        $html = $this->get('/generador-plantillas/globales?nueva')->assertOk()->getContent();
        preg_match('#window\.GeneradorPlantillas = (.*?);</script>#s', $html, $m);
        $config = json_decode($m[1], true);

        $this->assertSame('http://localhost/generador-plantillas/globales/api/', $config['api']);
        $this->assertSame('editor-plantillas:globales', $config['claveLocal']);
        $this->assertSame('http://localhost/maestros/plantillas', $config['volver']);
        $this->assertTrue($config['nueva']);
        $this->assertArrayNotHasKey('soloModificar', $config);
    }

    public function test_copiar_las_globales_al_dueno_sin_pisar_las_suyas(): void
    {
        $this->putJson($this->urlGlobal('recibo'), ['xml' => $this->xml('recibo'), 'version' => 0])->assertCreated();
        $this->putJson($this->urlGlobal('factura'), ['xml' => $this->xml('factura'), 'version' => 0])->assertCreated();

        $empresa = Empresa::conId(5);
        $this->como($empresa)->putJson($this->url('factura'), ['xml' => $this->xml('factura', '<pie altura="9"/>'), 'version' => 0])->assertCreated();

        $this->assertSame(['recibo'], GeneradorPlantillas::copiarGlobales($empresa));
        $this->assertSame([], GeneradorPlantillas::copiarGlobales($empresa));

        $this->assertSame(file_get_contents($this->carpeta . '/recibo.xml'), Plantilla::de($empresa)->llamada('recibo')->sole()->xml);
        $this->assertStringContainsString('<pie altura="9"/>', Plantilla::de($empresa)->llamada('factura')->sole()->xml);
        $this->assertFileExists($this->carpetaBlade . '/recibo-5.blade.php');
    }

    public function test_sin_crear_el_dueno_solo_modifica_las_que_tiene(): void
    {
        config()->set('generador-plantillas.crear', false);
        $empresa = Empresa::conId(5);
        $this->putJson($this->urlGlobal('recibo'), ['xml' => $this->xml('recibo'), 'version' => 0])->assertCreated();
        GeneradorPlantillas::copiarGlobales($empresa);
        $this->como($empresa);

        $this->putJson($this->url('recibo'), ['xml' => $this->xml('recibo', '<pie altura="9"/>'), 'version' => 1])->assertOk();
        $this->putJson($this->url('otra'), ['xml' => $this->xml('otra'), 'version' => 0])->assertForbidden();
        $this->postJson($this->url('recibo', 'duplicar'), ['nombre' => 'copia'])->assertForbidden();
        $this->deleteJson($this->url('recibo'))->assertForbidden();
        $this->assertSame(1, Plantilla::de($empresa)->count());

        $html = $this->get('/generador-plantillas?nueva')->assertOk()->getContent();
        preg_match('#window\.GeneradorPlantillas = (.*?);</script>#s', $html, $m);
        $config = json_decode($m[1], true);
        $this->assertTrue($config['soloModificar']);
        $this->assertArrayNotHasKey('nueva', $config);

        // En las globales, sí.
        $this->putJson($this->urlGlobal('otra'), ['xml' => $this->xml('otra'), 'version' => 0])->assertCreated();
    }
}
