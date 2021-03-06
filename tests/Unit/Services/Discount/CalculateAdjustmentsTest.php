<?php

declare(strict_types=1);

namespace Tipoff\Discounts\Tests\Unit\Services\Discount;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tipoff\Checkout\Models\Cart;
use Tipoff\Discounts\Models\Discount;
use Tipoff\Discounts\Tests\TestCase;
use Tipoff\Support\Enums\AppliesTo;
use Tipoff\TestSupport\Models\TestSellableBooking;

class CalculateAdjustmentsTest extends TestCase
{
    use DatabaseTransactions;

    private TestSellableBooking $sellable;
    private Cart $cart;

    public function setUp(): void
    {
        parent::setUp();

        TestSellableBooking::createTable();
        $this->sellable = TestSellableBooking::factory()->create();
        $this->cart = Cart::factory()->create();
    }

    /** @test */
    public function calculate_discount_with_no_discounts()
    {
        $this->withCart([
            [2500, 1],
        ], function ($cart) {
            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(0, $cart->getItemAmountTotal()->getDiscounts());
    }

    /** @test */
    public function calculate_discount_with_order_discounts()
    {
        $this->withCart([
            [2500, 1],
        ], function ($cart) {
            /** @var Discount $discount */
            $discount = Discount::factory()->amount(1000)->expired(false)->create([
                'code' => 'TESTCODE',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $discount->applyToCart($this->cart);
            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(1000, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(1500, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(1000, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(1500, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function calculate_discount_with_multiple_items()
    {
        $this->withCart([
            [2500, 1],
            [3500, 1],
        ], function ($cart) {
            /** @var Discount $discount */
            $discount = Discount::factory()->amount(1000)->expired(false)->create([
                'max_usage' => 2,
                'code' => 'TESTCODE',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $discount->applyToCart($this->cart);
            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(2000, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(4000, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(1000, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(1500, $cartItem->getAmountEach()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-1');
        $this->assertEquals(1000, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(2500, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function calculate_discount_with_limited_usage()
    {
        $this->withCart([
            [2000, 1],
            [3500, 1],
        ], function ($cart) {
            /** @var Discount $discount */
            $discount = Discount::factory()->percent(50)->expired(false)->create([
                'max_usage' => 1,
                'code' => 'TESTCODE',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $discount->applyToCart($this->cart);
            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(1750, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(3750, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(0, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(2000, $cartItem->getAmountEach()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-1');
        $this->assertEquals(1750, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(1750, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function calculate_percent_discount()
    {
        $this->withCart([
            [2500, 1],
        ], function ($cart) {
            /** @var Discount $discount */
            $discount = Discount::factory()->percent(10)->expired(false)->create([
                'code' => 'TESTCODE',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $discount->applyToCart($this->cart);
            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(250, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(2250, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(250, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(2250, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function active_auto_apply_discounts_are_included()
    {
        $this->withCart([
            [2500, 1],
        ], function ($cart) {
            Discount::factory()->amount(500)->autoApply()->expired(false)->create([
                'code' => 'CODE1',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            Discount::factory()->amount(500)->autoApply()->expired()->create([
                'code' => 'CODE2',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(500, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(2000, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(500, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(2000, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function ensure_discount_is_capped()
    {
        $this->withCart([
            [2500, 1],
        ], function ($cart) {
            /** @var Discount $code1 */
            $code1 = Discount::factory()->amount(2000)->expired(false)->create([
                'code' => 'CODE1',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $code1->applyToCart($this->cart);

            /** @var Discount $code2 */
            $code2 = Discount::factory()->amount(2000)->expired(false)->create([
                'code' => 'CODE2',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $code2->applyToCart($this->cart);
            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(2500, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(0, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(2500, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(0, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function ensure_amount_off_is_before_percent_off()
    {
        $this->withCart([
            [2500, 1],
        ], function ($cart) {
            /** @var Discount $code1 */
            $code1 = Discount::factory()->amount(1500)->expired(false)->create([
                'code' => 'CODE1',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $code1->applyToCart($this->cart);

            /** @var Discount $code2 */
            $code2 = Discount::factory()->percent(50)->expired(false)->create([
                'code' => 'CODE2',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $code2->applyToCart($this->cart);
            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(2000, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(500, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(2000, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(500, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function ensure_multiple_percent_off_use_discounted_value()
    {
        $this->withCart([
            [2000, 1],
        ], function ($cart) {
            /** @var Discount $code1 */
            $code1 = Discount::factory()->percent(50)->expired(false)->create([
                'code' => 'CODE1',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $code1->applyToCart($this->cart);

            /** @var Discount $code2 */
            $code2 = Discount::factory()->percent(50)->expired(false)->create([
                'code' => 'CODE2',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            $code2->applyToCart($this->cart);
            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(1500, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(500, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(1500, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(500, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function calculate_discount_with_participant_discounts()
    {
        $this->sellable->participants = 4;
        $this->withCart([
            [5500, 1],
        ], function ($cart) {
            /** @var Discount $discount */
            $discount = Discount::factory()->amount(1000)->expired(false)->create([
                'code' => 'TESTCODE',
                'applies_to' => AppliesTo::PARTICIPANT(),
            ]);

            $discount->applyToCart($cart);

            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(4000, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(1500, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(4000, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(1500, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    /** @test */
    public function calculate_discount_with_multiple_discounts()
    {
        $this->sellable->participants = 4;
        $this->withCart([
            [5500, 1],
        ], function ($cart) {
            /** @var Discount $orderCode */
            $orderCode = Discount::factory()->amount(1000)->expired(false)->create([
                'code' => 'CODE1',
                'applies_to' => AppliesTo::ORDER(),
            ]);

            /** @var Discount $participantCode */
            $participantCode = Discount::factory()->amount(1000)->expired(false)->create([
                'code' => 'CODE2',
                'applies_to' => AppliesTo::PARTICIPANT(),
            ]);

            $orderCode->applyToCart($cart);
            $participantCode->applyToCart($cart);

            Discount::calculateAdjustments($cart);
        });

        $cart = $this->cart;
        $this->assertEquals(5000, $cart->getItemAmountTotal()->getDiscounts());
        $this->assertEquals(500, $cart->getItemAmountTotal()->getDiscountedAmount());

        $cartItem = $cart->findItem($this->sellable, 'item-0');
        $this->assertEquals(5000, $cartItem->getAmountEach()->getDiscounts());
        $this->assertEquals(500, $cartItem->getAmountEach()->getDiscountedAmount());
    }

    private function addCartItems(array $items): Cart
    {
        foreach ($items as $idx => $item) {
            [$amount, $quantity] = $item;

            $this->cart->upsertItem(
                Cart::createItem($this->sellable, "item-{$idx}", $amount, $quantity)
            );
        }

        return $this->cart;
    }

    private function withCart(array $items, \Closure $closure)
    {
        $result = ($closure)($this->addCartItems($items));

        // Save results so we can inspect
        $this->cart->cartItems->each->save();
        $this->cart->save();

        return $result;
    }
}
