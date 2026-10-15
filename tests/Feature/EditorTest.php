<?php

namespace InformaticadosLago\GeneradorPlantillas\Tests\Feature;

use InformaticadosLago\GeneradorPlantillas\Tests\Modelos\Empresa;
use InformaticadosLago\GeneradorPlantillas\Tests\TestCase;

class EditorTest extends TestCase
{
    /** Lo que el controlador mete en window.GeneradorPlantillas. */
    private function config(string $html): array
    {
        $this->assertSame(1, preg_match('#<script>window\.GeneradorPlantillas = (.*?);</script>\s*</head>#s', $html, $m),
            'La configuración tiene que ir justo antes de </head>, antes del script del editor.');

        return json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_sirve_el_editor_con_la_configuracion(): void
    {
        $respuesta = $this->get('/generador-plantillas')->assertOk();
        $html      = $respuesta->getContent();

        $this->assertStringStartsWith('text/html', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('{{emisor.nombre}}', $html, 'Los {{campo}} del editor no se tocan.');

        $config = $this->config($html);
        $this->assertSame('http://localhost/generador-plantillas/api/', $config['api']);
        $this->assertNotEmpty($config['csrf']);
        $this->assertSame('editor-plantillas:comunes', $config['claveLocal']);
        $this->assertArrayNotHasKey('campos', $config, 'Sin campos en la configuración, el editor usa los suyos.');
    }

    public function test_pasa_los_campos_del_programa_y_un_borrador_por_plantillable(): void
    {
        config()->set('generador-plantillas.campos', ['recibo' => ['numero', 'fecha'], 'inquilino' => ['nombre']]);
        config()->set('generador-plantillas.listas', ['lineas' => ['concepto', 'importe']]);
        config()->set('generador-plantillas.datos_prueba', ['recibo' => ['numero' => '</script><b>7']]);

        $config = $this->config($this->como(Empresa::conId(5))->get('/generador-plantillas')->assertOk()->getContent());

        $this->assertSame(['recibo' => ['numero', 'fecha'], 'inquilino' => ['nombre']], $config['campos']);
        $this->assertSame(['lineas' => ['concepto', 'importe']], $config['listas']);
        $this->assertSame('</script><b>7', $config['datosPrueba']['recibo']['numero']);
        $this->assertSame('editor-plantillas:' . Empresa::class . ':5', $config['claveLocal']);
    }

    public function test_el_prefijo_de_las_rutas_se_configura(): void
    {
        $this->assertSame('http://localhost/generador-plantillas', route('generador-plantillas.editor'));
        $this->assertSame('http://localhost/generador-plantillas/api/plantillas/factura', route('generador-plantillas.api.ver', 'factura'));
    }

    public function test_abre_la_plantilla_de_la_url_o_una_nueva(): void
    {
        $config = $this->config($this->get('/generador-plantillas?plantilla=' . rawurlencode(' recibo alquiler '))->getContent());
        $this->assertSame('recibo alquiler', $config['abrir']);
        $this->assertArrayNotHasKey('nueva', $config);

        $config = $this->config($this->get('/generador-plantillas?nueva')->getContent());
        $this->assertTrue($config['nueva']);
        $this->assertArrayNotHasKey('abrir', $config);

        // Con las dos, manda la plantilla.
        $config = $this->config($this->get('/generador-plantillas?nueva&plantilla=factura')->getContent());
        $this->assertSame('factura', $config['abrir']);
        $this->assertArrayNotHasKey('nueva', $config);

        $config = $this->config($this->get('/generador-plantillas')->getContent());
        $this->assertArrayNotHasKey('abrir', $config);
        $this->assertArrayNotHasKey('nueva', $config);
    }

    public function test_volver_solo_si_el_programa_lo_configura(): void
    {
        $this->assertArrayNotHasKey('volver', $this->config($this->get('/generador-plantillas')->getContent()));

        config()->set('generador-plantillas.volver', 'empresa/plantillas');

        $config = $this->config($this->get('/generador-plantillas')->getContent());
        $this->assertSame('http://localhost/empresa/plantillas', $config['volver']);
    }
}
