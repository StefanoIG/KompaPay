<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuids
{
    /**
     * Boot the trait to set up listeners for creating and saving models.
     *
     * @return void
     */
    protected static function bootHasUuids()
    {
        static::creating(function ($model) {
            // Si la clave primaria no está establecida, generamos un UUID
            if (! $model->getKey()) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Indica que el ID del modelo no es autoincremental.
     *
     * @return bool
     */
    public function getIncrementing()
    {
        return false;
    }

    /**
     * Indica que el tipo de la clave primaria es una cadena.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'string';
    }
}