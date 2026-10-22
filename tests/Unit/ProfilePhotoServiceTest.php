<?php

namespace Tests\Unit;

use App\Services\ProfilePhotoService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProfilePhotoServiceTest extends TestCase
{
    private const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLhGQAAAABJRU5ErkJggg==';

    private ProfilePhotoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->service = app(ProfilePhotoService::class);
    }

    public function test_guarda_imagen_base64_en_disco_privado(): void
    {
        $path = $this->service->storeIfBase64(self::PNG);

        $this->assertMatchesRegularExpression('#^avatars/[0-9a-f-]{36}\.png$#', $path);
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_al_cambiar_la_foto_se_borra_la_anterior(): void
    {
        $primera = $this->service->storeIfBase64(self::PNG);
        $segunda = $this->service->storeIfBase64(self::PNG, $primera);

        Storage::disk('local')->assertMissing($primera);
        Storage::disk('local')->assertExists($segunda);
    }

    public function test_retorna_valor_original_si_no_es_base64(): void
    {
        $this->assertSame('/storage/avatar.png', $this->service->storeIfBase64('/storage/avatar.png'));
        $this->assertNull($this->service->storeIfBase64(null));
    }

    public function test_lanza_validacion_si_la_imagen_base64_es_invalida(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->storeIfBase64('data:image/png;base64,no-es-base64-valido');
    }

    public function test_public_value_usa_la_ruta_autorizada(): void
    {
        $url = $this->service->publicValue('avatars/3f2b6c1e-1111-4222-8333-444455556666.png', 7);

        $this->assertStringContainsString('/archivos/fotos/7?v=', $url);
        $this->assertNull($this->service->publicValue('avatars/x.png'), 'Sin dueno no hay URL');
        $this->assertSame('https://lh3.googleusercontent.com/a.png', $this->service->publicValue('https://lh3.googleusercontent.com/a.png', 7));
    }

    public function test_foto_antigua_del_disco_publico_se_mueve_al_privado(): void
    {
        $path = 'avatars/3f2b6c1e-1111-4222-8333-444455556666.png';
        Storage::disk('public')->put($path, 'png');

        $this->assertSame($path, $this->service->rutaPrivada('/storage/'.$path));
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }
}
