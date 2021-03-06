<?php

declare(strict_types=1);

namespace Tipoff\Discounts\Services\Discount;

use Tipoff\Discounts\Models\Discount;
use Tipoff\Support\Contracts\Checkout\CartInterface;
use Tipoff\Support\Contracts\Checkout\CartItemInterface;
use Tipoff\Support\Contracts\Sellable\Booking;
use Tipoff\Support\Enums\AppliesTo;

class CalculateAdjustments
{
    public function __invoke(CartInterface $cart): void
    {
        $discounts = Discount::query()->byCartId($cart->getId(), true)->get();

        $discounts
            ->sort(function (Discount $discount) {
                // Sort so All Amount off are applied before percent off
                return $discount->amount > 0 ? 0 : 1;
            })
            ->each(function (Discount $discount) use ($cart) {
                $cart->getItems()
                    ->sortByDesc(function (CartItemInterface $cartItem) {
                        // Sort ensures limited use discounts apply to most expensive item
                        return $cartItem->getAmountTotal()->getDiscountedAmount();
                    })
                    ->take($discount->max_usage)    // Enforce usage limitations
                    ->each(function (CartItemInterface $cartItem) use ($discount) {
                        $this->calculateItemDiscount($cartItem, $discount);
                    });
            });
    }

    protected function calculateItemDiscount(CartItemInterface $cartItem, Discount $discount): self
    {
        $sellable = $cartItem->getSellable();
        $amount = $cartItem->getAmountEach();
        $discountAmount = 0;

        if ($discount->percent) {
            $discountAmount = ($amount->getDiscountedAmount() * $discount->percent) / 100;
        } elseif ($discount->applies_to === AppliesTo::ORDER()) {
            $discountAmount = ($discount->amount / $cartItem->getQuantity());
        } elseif ($discount->applies_to === AppliesTo::PARTICIPANT() && $sellable instanceof Booking) {
            $discountAmount = $discount->amount * $sellable->getParticipants();
        }

        $cartItem->setAmountEach($amount->addDiscounts((int) $discountAmount));

        return $this;
    }
}
