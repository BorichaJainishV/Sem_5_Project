<?php

use PHPUnit\Framework\TestCase;

final class CustomDesignSessionTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
        sync_custom_design_cart_quantity();
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        sync_custom_design_cart_quantity();
    }

    public function testAddAndCountCustomDesigns(): void
    {
        add_custom_design_id(10);
        add_custom_design_id(11);

        $this->assertSame([10, 11], get_custom_design_ids());
        $this->assertSame(2, custom_design_count());
        $this->assertSame(2, $_SESSION['cart'][4] ?? 0);
    }

    public function testDuplicateDesignIsIgnored(): void
    {
        add_custom_design_id(15);
        add_custom_design_id(15);

        $this->assertSame([15], get_custom_design_ids());
        $this->assertSame(1, custom_design_count());
    }

    public function testRemoveDesign(): void
    {
        add_custom_design_id(21);
        add_custom_design_id(22);
        remove_custom_design_id(21);

        $this->assertSame([22], get_custom_design_ids());
        $this->assertSame(1, custom_design_count());
        $this->assertSame(1, $_SESSION['cart'][4] ?? 0);
    }

    public function testClearDesignsResetsCartEntry(): void
    {
        add_custom_design_id(33);
        add_custom_design_id(34);

        clear_custom_design_ids();
        $this->assertSame([], get_custom_design_ids());
        $this->assertSame(0, custom_design_count());
        $this->assertArrayNotHasKey(4, $_SESSION['cart']);
    }
}
