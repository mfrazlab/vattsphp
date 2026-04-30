<?php

namespace Vatts\Rpc\Attributes;

use Attribute;

/**
 * Marca um método como seguro para ser chamado via RPC pelo Front-End.
 * Equivalente ao "Expose()" do seu código TypeScript.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Expose
{
}

