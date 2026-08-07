<?php

namespace App\Service\Shop;

use App\Entity\Shop\Order;
use App\Infrastructure\Entity\Shop\Product;
use App\Infrastructure\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * @codeCoverageIgnore
 */
readonly class ShopStripeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private string $secretKey,
    ) {
    }

    /**
     * @throws ApiErrorException
     */
    public function createCheckoutSession(User $user, string $ref, mixed $domain): string
    {
        $order = $this->em->getRepository(Order::class)->findOneBy(['reference' => $ref]);
        if (!$order instanceof Order) {
            throw new RuntimeException('Error in order process (create shipping).');
        }

        $productsForStripe = $this->createLineItems($order, $domain);
        $shippingForStripe = $this->createShipping($order);

        $stripe = new StripeClient($this->secretKey);

        $params = [
            'line_items' => $productsForStripe,
            'shipping_options' => $shippingForStripe,
            'mode' => 'payment',
            'client_reference_id' => $order->getId(),
            'customer_email' => $user->getEmail(),
            'success_url' => $domain . '/order/success/{CHECKOUT_SESSION_ID}',
            'cancel_url' => $domain . '/order/cancel/{CHECKOUT_SESSION_ID}',
        ];

        $checkoutSession = $stripe->checkout->sessions->create($params);

        // enregistrer le checkout session id dans la commande
        $order->setStripeSessionId($checkoutSession->id);

        $this->em->flush();

        return $checkoutSession->url;
    }

    private function createShipping(Order $order): array
    {
        // infos de livraison pour stripe
        return [
            'shipping_rate_data' => [
                'display_name' => $order->getCarrierName(),
                'type' => 'fixed_amount',
                'fixed_amount' => [
                    'amount' => $order->getCarrierPrice(),
                    'currency' => 'eur',
                ],
            ],
        ];
    }

    private function createLineItems(Order $order, string $domain): array
    {
        $productsForStripe = [];

        // infos de chaque ligne de commande pour stripe
        foreach ($order->getOrderDetails() as $details) {
            $product = $this->em->getRepository(Product::class)->findOneBy(
                ['title' => $details->getProduct()]
            );

            if (!$product instanceof Product) {
                throw new RuntimeException('Error in order process (create line items).');
            }

            $productsForStripe[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $details->getPrice(),
                    'product_data' => [
                        'name' => $details->getProduct(),
                        'description' => $product->getDescription(),
                        'images' => [
                            $domain . '/uploads/images/product/' . $product->getImageName(),
                        ],
                    ],
                ],
                'quantity' => $details->getQuantity(),
            ];
        }

        return $productsForStripe;
    }
}
