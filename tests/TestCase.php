<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function checkoutFields(array $fields): array
    {
        $page = $this->get(route('checkout.index'))->assertOk();

        return $fields + ['checkout_version' => $page->viewData('checkoutVersion')];
    }
}
