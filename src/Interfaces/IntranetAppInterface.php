<?php

namespace Hwkdo\IntranetAppBase\Interfaces;

use Illuminate\Support\Collection;

interface IntranetAppInterface
{
    public function roles_admin(): Collection;
    public function roles_user(): Collection;
    public function identifier(): string;
}