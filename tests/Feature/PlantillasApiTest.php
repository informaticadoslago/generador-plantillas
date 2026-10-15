<?php

namespace InformaticadosLago\GeneradorPlantillas\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use InformaticadosLago\GeneradorPlantillas\Models\Plantilla;
use InformaticadosLago\GeneradorPlantillas\Tests\Modelos\Comunidad;
use InformaticadosLago\GeneradorPlantillas\Tests\Modelos\Empresa;
use InformaticadosLago\GeneradorPlantillas\Tests\TestCase;

class PlantillasApiTest extends TestCase
{
    public function test_la_migracion_crea_la_tabla_polimorfica(): void
    {
        $this->assertTrue(Schema::hasColumns('plantillas', [
            'id', 'plantillable_type', 'plantillable_id', 'nombre', 'xml', 'version', 'creada', 'actualizada',
        ]));
    }

    public function test_guardar_crea_y_luego_sube_la_version(): void
    {
        $this->getJson($this->url())->assertOk()->assertExactJson(['plantillas' => []]);

        $this->putJson($this->url('factura'), ['xml' => $this->xml('factura'), 'version' => 0])
            ->assertCreated()
            ->assertJson(['nombre' => 'factura', 'version' => 1]);

        $this->putJson($this->url('factura'), ['xml' => $this->xml('factura', '<cabecera altura="70"/>'), 'version' => 1])
            ->assertOk()
            ->assertJson(['nombre' => 'factura', 'version' => 2]);

        $this->getJson($this->url('factura'))
            ->assertOk()
            ->assertJson(['nombre' => 'factura', 'version' => 2, 'xml' => $this->xml('factura', '<cabecera altura="70"/>')]);

        $this->getJson($this->url())
            ->assertOk()
            ->assertJsonCount(1, 'plantillas')
            ->assertJsonPath('plantillas.0.nombre', 'factura')
            ->assertJsonPath('plantillas.0.version', 2);
    }

    public function test_con_otra_version_responde_409_y_no_guarda(): void
    {
        $this->putJson($this->url('factura'), ['xml' => $this->xml('factura'), 'version' => 0])->assertCreated();
        $this->putJson($this->url('factura'), ['xml' => $this->xml('factura'), 'version' => 1])->assertOk();

        // Otro equipo la tenía abierta en la versión 1.
        $this->putJson($this->url('factura'), ['xml' => $this->xml('factura', '<pie altura="10"/>'), 'version' => 1])
            ->assertStatus(409)
            ->assertJson(['codigo' => 'version', 'version_actual' => 2]);

        // Nueva (version 0) con un nombre que ya existe.
        $this->putJson($this->url('factura'), ['xml' => $this->xml('factura'), 'version' => 0])
            ->assertStatus(409)
            ->assertJson(['codigo' => 'existe', 'version_actual' => 2]);

        $this->assertSame($this->xml('factura'), Plantilla::sole()->xml);
        $this->assertSame(2, Plantilla::sole()->version);
    }

    public function test_el_nombre_no_distingue_mayusculas(): void
    {
        $this->putJson($this->url('Factura'), ['xml' => $this->xml('Factura'), 'version' => 0])->assertCreated();

        $this->getJson($this->url('factura'))->assertOk()->assertJson(['nombre' => 'Factura']);
        $this->putJson($this->url('FACTURA'), ['xml' => $this->xml('FACTURA'), 'version' => 0])
            ->assertStatus(409)
            ->assertJson(['codigo' => 'existe']);
    }

    public function test_nombres_con_espacios_y_puntos(): void
    {
        $this->putJson($this->url('factura 2.v1'), ['xml' => $this->xml('factura 2.v1'), 'version' => 0])->assertCreated();

        $this->getJson($this->url('factura 2.v1'))->assertOk()->assertJson(['nombre' => 'factura 2.v1']);
    }

    public function test_rechaza_xml_no_valido(): void
    {
        $casos = [
            'vacío'                       => '',
            'mal formado'                 => '<plantilla nombre="factura">',
            'otra raíz'                   => '<informe nombre="factura"/>',
            'nombre distinto del de la URL' => $this->xml('otra'),
            'con DOCTYPE'                 => '<!DOCTYPE plantilla><plantilla nombre="factura"/>',
            'logo con base64 roto'        => $this->xml('factura', '<cabecera><logo nombre="a.png" tipo="image/png">no*es*base64</logo></cabecera>'),
            'logo con tipo no admitido'   => $this->xml('factura', '<cabecera><logo nombre="a.exe" tipo="application/x-msdownload">QUJD</logo></cabecera>'),
        ];
        foreach ($casos as $caso => $xml) {
            $this->putJson($this->url('factura'), ['xml' => $xml, 'version' => 0])
                ->assertStatus(422, "Caso: $caso");
        }
        $this->putJson($this->url('mal*nombre'), ['xml' => $this->xml('mal*nombre'), 'version' => 0])->assertStatus(422);
        $this->putJson($this->url('-empieza-con-guion'), ['xml' => $this->xml('-empieza-con-guion'), 'version' => 0])->assertStatus(422);

        $this->assertSame(0, Plantilla::count());
    }

    public function test_acepta_un_logo_en_base64(): void
    {
        $png = base64_encode(random_bytes(30));
        $this->putJson($this->url('factura'), [
            'xml'     => $this->xml('factura', '<cabecera><logo nombre="logo.png" tipo="image/png" x="15" y="10" ancho="40" alto="20">' . $png . '</logo></cabecera>'),
            'version' => 0,
        ])->assertCreated();
    }

    public function test_guardar_exige_json(): void
    {
        $this->put($this->url('factura'), ['xml' => $this->xml('factura'), 'version' => 0])->assertStatus(415);
    }

    public function test_descarga_el_xml(): void
    {
        $this->putJson($this->url('factura 2'), ['xml' => $this->xml('factura 2'), 'version' => 0])->assertCreated();

        $respuesta = $this->get($this->url('factura 2', 'xml'))->assertOk();
        $this->assertSame($this->xml('factura 2'), $respuesta->getContent());
        $this->assertStringStartsWith('application/xml', $respuesta->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="factura_2.xml"', $respuesta->headers->get('Content-Disposition'));
    }

    public function test_duplicar_copia_con_el_nombre_nuevo_dentro_del_xml(): void
    {
        $this->putJson($this->url('factura'), ['xml' => $this->xml('factura'), 'version' => 0])->assertCreated();

        $this->postJson($this->url('factura', 'duplicar'), ['nombre' => 'factura copia'])
            ->assertCreated()
            ->assertJson(['nombre' => 'factura copia', 'version' => 1]);

        $copia = $this->getJson($this->url('factura copia'))->assertOk()->json('xml');
        $this->assertSame('factura copia', simplexml_load_string($copia)['nombre']->__toString());

        $this->postJson($this->url('factura', 'duplicar'), ['nombre' => 'Factura Copia'])
            ->assertStatus(409)
            ->assertJson(['codigo' => 'existe']);
        $this->postJson($this->url('no-existe', 'duplicar'), ['nombre' => 'otra'])->assertNotFound();
    }

    public function test_borrar(): void
    {
        $this->putJson($this->url('factura'), ['xml' => $this->xml('factura'), 'version' => 0])->assertCreated();

        $this->deleteJson($this->url('factura'))->assertOk()->assertJson(['borrada' => 'factura']);
        $this->deleteJson($this->url('factura'))->assertNotFound();
        $this->getJson($this->url('factura'))->assertNotFound();
        $this->get($this->url('factura', 'xml'))->assertNotFound();
    }

    public function test_cada_plantillable_ve_solo_las_suyas(): void
    {
        $empresa5   = Empresa::conId(5);
        $empresa8   = Empresa::conId(8);
        $comunidad5 = Comunidad::conId(5);

        $this->como($empresa5)->putJson($this->url('recibo'), ['xml' => $this->xml('recibo'), 'version' => 0])->assertCreated();
        // El mismo nombre en otro dueño es otra plantilla, también con el mismo id pero de otro tipo.
        $this->como($empresa8)->putJson($this->url('recibo'), ['xml' => $this->xml('recibo'), 'version' => 0])->assertCreated();
        $this->como($comunidad5)->putJson($this->url('recibo'), ['xml' => $this->xml('recibo'), 'version' => 0])->assertCreated();
        $this->como($comunidad5)->putJson($this->url('balance'), ['xml' => $this->xml('balance'), 'version' => 0])->assertCreated();

        $this->como($empresa5)->getJson($this->url())->assertJsonCount(1, 'plantillas');
        $this->como($comunidad5)->getJson($this->url())->assertJsonCount(2, 'plantillas');
        $this->como(null)->getJson($this->url())->assertJsonCount(0, 'plantillas');

        // La empresa 5 no ve ni toca el balance de la comunidad 5.
        $this->como($empresa5)->getJson($this->url('balance'))->assertNotFound();
        $this->como($empresa5)->deleteJson($this->url('balance'))->assertNotFound();

        // Guardar en la empresa 5 no sube la versión de las otras.
        $this->como($empresa5)->putJson($this->url('recibo'), ['xml' => $this->xml('recibo'), 'version' => 1])->assertOk()->assertJson(['version' => 2]);
        $this->como($empresa8)->getJson($this->url('recibo'))->assertJson(['version' => 1]);

        $recibo = Plantilla::de($comunidad5)->llamada('recibo')->sole();
        $this->assertSame(Comunidad::class, $recibo->plantillable_type);
        $this->assertSame(5, (int) $recibo->plantillable_id);
        $this->assertSame(4, Plantilla::count());
    }

    public function test_sin_plantillable_trabaja_con_las_comunes(): void
    {
        $this->putJson($this->url('recibo'), ['xml' => $this->xml('recibo'), 'version' => 0])->assertCreated();

        $comun = Plantilla::sole();
        $this->assertNull($comun->plantillable_type);
        $this->assertNull($comun->plantillable_id);
        $this->como(Empresa::conId(5))->getJson($this->url('recibo'))->assertNotFound();
    }
}
