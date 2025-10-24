<?php

declare(strict_types=1);

use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppHwro\IntranetAppHwro;
use Hwkdo\IntranetAppRaumverwaltung\IntranetAppRaumverwaltung;
use Hwkdo\IntranetAppTemplate\IntranetAppTemplate;

test('IntranetAppHwro implements interface with settings methods', function () {
    expect(IntranetAppHwro::class)->toImplement(IntranetAppInterface::class);
    expect(method_exists(IntranetAppHwro::class, 'userSettingsClass'))->toBeTrue();
    expect(method_exists(IntranetAppHwro::class, 'appSettingsClass'))->toBeTrue();
});

test('IntranetAppRaumverwaltung implements interface with settings methods', function () {
    expect(IntranetAppRaumverwaltung::class)->toImplement(IntranetAppInterface::class);
    expect(method_exists(IntranetAppRaumverwaltung::class, 'userSettingsClass'))->toBeTrue();
    expect(method_exists(IntranetAppRaumverwaltung::class, 'appSettingsClass'))->toBeTrue();
});

test('IntranetAppTemplate implements interface with settings methods', function () {
    expect(IntranetAppTemplate::class)->toImplement(IntranetAppInterface::class);
    expect(method_exists(IntranetAppTemplate::class, 'userSettingsClass'))->toBeTrue();
    expect(method_exists(IntranetAppTemplate::class, 'appSettingsClass'))->toBeTrue();
});

test('IntranetAppHwro returns correct settings classes', function () {
    expect(IntranetAppHwro::userSettingsClass())->not->toBeNull()
        ->and(IntranetAppHwro::appSettingsClass())->not->toBeNull()
        ->and(class_exists(IntranetAppHwro::userSettingsClass()))->toBeTrue()
        ->and(class_exists(IntranetAppHwro::appSettingsClass()))->toBeTrue();
});

test('apps without settings return null', function () {
    expect(IntranetAppRaumverwaltung::userSettingsClass())->toBeNull()
        ->and(IntranetAppRaumverwaltung::appSettingsClass())->toBeNull()
        ->and(IntranetAppTemplate::userSettingsClass())->toBeNull()
        ->and(IntranetAppTemplate::appSettingsClass())->toBeNull();
});

