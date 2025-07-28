<?php

namespace Hwkdo\IntranetAppBase\Traits;

trait hasEnumTrait
{
    public static function getAllValues(): array
    {
        return array_column(self::cases(),'value');
    }

    public static function getSelectOptions()
    {
        $result = [];
        $result[] = [
            'id' => '',
            'name' => 'Bitte wählen'
        ];
        foreach(self::cases() as $case)
        {
            $result[] = [
                'id' => $case->value,
                'name' => $case->value
            ];
        }
        return collect($result);
    }
}
