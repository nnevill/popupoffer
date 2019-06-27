<?php

namespace Drupal\popup_offer\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_promotion\Entity\CouponInterface;
use Drupal\commerce\ConditionManagerInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_order\PriceSplitterInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\RounderInterface;
use Drupal\commerce_promotion\Entity\PromotionInterface;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\OrderPromotionOfferBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Porvides a special offer accepted in the popup.
 *
 *
 * @CommercePromotionOffer(
 *   id = "popup_offer",
 *   label = @Translation("Popup Offer"),
 *   entity_type = "commerce_order",
 * )
 */
class PopupOffer extends OrderPromotionOfferBase
{

  /**
   * The condition manager.
   *
   * @var \Drupal\commerce\ConditionManagerInterface
   */
  protected $conditionManager;
  /**
   * @var EntityManager
   */
  private $entityManager;
  /**
   * @var UserDataInterface
   */
  private $userData;
  /**
   * @var AccountProxyInterface
   */
  private $currentUser;


  /**
   * Constructs a new BuyXGetY object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The pluginId for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_price\RounderInterface $rounder
   *   The rounder.
   * @param \Drupal\commerce_order\PriceSplitterInterface $splitter
   *   The splitter.
   * @param \Drupal\commerce\ConditionManagerInterface $condition_manager
   *   The condition manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RounderInterface $rounder, PriceSplitterInterface $splitter,
                              ConditionManagerInterface $condition_manager, EntityTypeManagerInterface $entityManager, UserDataInterface $userData, AccountProxyInterface $currentUser)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $rounder, $splitter);

    $this->conditionManager = $condition_manager;
    $this->entityManager = $entityManager;
    $this->userData = $userData;
    $this->currentUser = $currentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('commerce_price.rounder'),
        $container->get('commerce_order.price_splitter'),
        $container->get('plugin.manager.commerce_condition'),
        $container->get('entity_type.manager'),
        $container->get('user.data'),
        $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return [
            'main_product' => '0',
            'discount_product1' => '0',
            'discount_product2' => '0',
            'discount_product3' => '0',
            'discount_product4' => '0',
            'discount_value' => 0,
        ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $form += parent::buildConfigurationForm($form, $form_state);

    // Remove the main fieldset.
    $form['#type'] = 'container';

    $form['product'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Target product'),
        '#collapsible' => FALSE,
    ];
    $form['product']['name'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Product name'),
        '#default_value' => $this->getProductNameByID([$this->configuration['main_product']]),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'commerce_product',
    ];

    $form['discount'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Discount products'),
        '#collapsible' => FALSE,
    ];
    $form['discount']['product1'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Name of discount product'),
        '#default_value' => $this->getProductNameByID([$this->configuration['discount_product1']]),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'commerce_product',
    ];
    $form['discount']['product2'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Name of discrount product'),
        '#default_value' => $this->getProductNameByID([$this->configuration['discount_product2']]),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'commerce_product',
    ];
    $form['discount']['product3'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Name of discount product'),
        '#default_value' => $this->getProductNameByID([$this->configuration['discount_product3']]),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'commerce_product',
    ];
    $form['discount']['product4'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Name of discount product'),
        '#default_value' => $this->getProductNameByID([$this->configuration['discount_product4']]),
        '#type' => 'entity_autocomplete',
        '#target_type' => 'commerce_product',
    ];
    $form['discount']['value'] = [
        '#type' => 'commerce_number',
        '#title' => $this->t('Enter the value of discount in % from 0 to 100'),
        '#default_value' => Calculator::multiply((string)$this->configuration['discount_value'], 100),
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateConfigurationForm($form, $form_state);
    $values = $form_state->getValue($form['#parents']);
    if (!is_numeric($values['product']['name']) || !is_numeric($values['product']['name'])) {
      $form_state->setError($form, $this->t('You should specify target product and discounts.'));
    }
    if (!is_numeric($values['discount']['value'])) {
      $form_state->setError($form, $this->t('Please specify discount value from 0 to 100'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
  {

    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['main_product'] = $values['product']['name'];
      $this->configuration['discount_product1'] = $values['discount']['product1'];
      $this->configuration['discount_product2'] = $values['discount']['product2'];
      $this->configuration['discount_product3'] = $values['discount']['product3'];
      $this->configuration['discount_product4'] = $values['discount']['product4'];
      $this->configuration['discount_value'] = Calculator::divide((string)$values['discount']['value'], '100');

    }
  }

  /**
   * {@inheritdoc}
   */
  public function apply(EntityInterface $entity, PromotionInterface $promotion, CouponInterface $coupon = NULL)
  {
    $this->assertEntity($entity);
    $order = $entity;
    $order_items = $order->getItems();
    /* @var OrderItemInterface $lastOrderItem */
    $lastOrderItem = $order_items[count($order_items) - 1];

    $variantId = $lastOrderItem->getPurchasedEntityId();
    $configs = $this->configuration;
    /* @var Product $mainProduct */
    $mainProduct = $this->entityManager->getStorage('commerce_product')->load($configs['main_product']);
    $mainProductVariations = $mainProduct->getVariationIds();
    if (in_array($variantId, $mainProductVariations)) {
      $discountProductsIds = [];
      foreach ($configs as $key => $value) {
        if (stripos($key, 'discount_product') !== FALSE && $value > 0) {
          $discountProductsIds[] = $value;
        }
      }

      $data['products'] = $this->getProductNameByID($discountProductsIds);
      $data['variations'] = $this->getProductsVariant($data['products'], $configs['discount_value']);
      $data['discount'] = $configs['discount_value'];

      $this->userData->set('popup_offer', $this->currentUser->id(), 'discount_products', $data);
    }
  }


  /**
   * Returns product value(s) by id(s).
   *
   * @param array $ids
   * @return bool|EntityInterface|EntityInterface[]|null
   */
  protected function getProductNameByID($ids = [])
  {
    $cnt = count($ids);
    if ($cnt == 1) {
      $entity = $this->entityManager->getStorage('commerce_product')->load($ids[0]);
      if (!is_null($entity)) {
        return $entity;
      }
    } elseif ($cnt > 1) {
      $entities = $this->entityManager->getStorage('commerce_product')->loadMultiple($ids);
      return $entities;
    }
    return FALSE;
  }

  /**
   * Get product variants with adjusted price for the popup window.
   *
   * @param array $products
   * @param int $discount
   */
  protected function getProductsVariant($products = [], $discount = 1) {
    $variations = [];
    if (count($products) > 0) {
      /* @var \Drupal\commerce_product\Entity\Product $product */
      foreach ($products as $product) {
        /* @var ProductVariation $variation */
        $variation = $this->entityManager->getStorage('commerce_product_variation')->load($product->getVariationIds()[0]);

        $multiplier = (string) (1 - $discount);
        $variation->setPrice($variation->getPrice()->multiply($multiplier));
        $variations[] = $variation;
      }
    }
    return $variations;
  }
}
