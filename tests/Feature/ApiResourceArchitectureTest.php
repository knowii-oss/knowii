<?php

use App\Http\Resources\AbstractKnowiiJsonResource;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Model;

/**
 * @return list<class-string<Model>>
 */
function knowiiModels(): array
{
    $models = [];
    foreach (glob(dirname(__DIR__, 2).'/app/Models/*.php') ?: [] as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');
        if (is_subclass_of($class, Model::class)) {
            $models[] = $class;
        }
    }

    return $models;
}

test('every model with an API resource declares it with #[UseResource]', function () {
    $checked = 0;
    foreach (knowiiModels() as $model) {
        $resource = 'App\\Http\\Resources\\'.class_basename($model).'Resource';
        if (! class_exists($resource)) {
            continue;
        }

        $attributes = (new ReflectionClass($model))->getAttributes(UseResource::class);
        expect($attributes)->toHaveCount(1, "{$model} has no #[UseResource]")
            ->and($attributes[0]->getArguments()[0])->toBe($resource, "{$model} does not use {$resource}");
        $checked++;
    }

    expect($checked)->toBeGreaterThan(10);
});

test('toResource() returns the declared resource', function () {
    foreach (knowiiModels() as $model) {
        $attributes = (new ReflectionClass($model))->getAttributes(UseResource::class);
        if ($attributes === []) {
            continue;
        }

        expect((new $model)->toResource())->toBeInstanceOf($attributes[0]->getArguments()[0]);
    }
});

test('the API resources take the model as their only constructor argument', function () {
    foreach (glob(dirname(__DIR__, 2).'/app/Http/Resources/*.php') ?: [] as $file) {
        $class = 'App\\Http\\Resources\\'.basename($file, '.php');
        expect(is_a($class, AbstractKnowiiJsonResource::class, true))->toBeTrue("{$class} does not extend AbstractKnowiiJsonResource")
            ->and((new ReflectionClass($class))->getConstructor()?->getDeclaringClass()->getName())->not->toBe(AbstractKnowiiJsonResource::class)
            ->and((new ReflectionClass($class))->getConstructor()?->getNumberOfParameters())->toBe(1);
    }
});

test('application code creates resources with toResource() and toResourceCollection()', function () {
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (preg_match('/new \w+Resource\(/', $source) === 1) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBe([]);
});
