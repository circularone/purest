<?php

namespace Drupal\purest\Normalizer;

use Drupal\serialization\Normalizer\TypedDataNormalizer as SerializationTypedDataNormalizer;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\file\Entity\File;

/**
 * Converts typed data objects to arrays.
 */
class ImageNormalizer extends SerializationTypedDataNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = ImageItem::class;

  /**
   * {@inheritdoc}
   */
  public function normalize($object, $format = NULL, array $context = []) {
    $value = $object->getValue();

    $parent_entity = $object->getEntity();
    $parent_config_name = 'purest.normalizer';
    $parent_config_name .= '.' . $parent_entity->getEntityTypeId();
    $parent_config_name .= '.' . $parent_entity->bundle();
    $config = \Drupal::service('config.factory')->get($parent_config_name);

    $fields_config = $config->get('fields');
    $field_config = FALSE;

    $field_definition = $object->getFieldDefinition();
    $field_name = $field_definition->getName();

    if ($fields_config !== NULL && isset($fields_config[$field_name])) {
      $field_config = $fields_config[$field_name];
    }

    $formats = [
      'meta_multiple_meta',
      'meta_single_meta',
      'meta_multiple',
      'meta_single',
      'multiple_meta',
      'single_meta',
      'multiple',
      'single',
    ];

    $image_format = $formats[0];

    if (isset($field_config['format']) && !empty($field_config['format']) && in_array($field_config['format'], $formats)) {
      $image_format = $field_config['format'];
    }

    $output = NULL;

    $fields = ['alt', 'uuid', 'title', 'styles', 'url', 'mime_type', 'width', 'height', 'original'];

    if (isset($value['target_id'])) {
      $allow_props = ['uuid', 'alt', 'title', 'fid'];
      $file = File::load($value['target_id']);
      $file_uri = $file->getFileUri();

      $output = [
        'uuid' => $file->uuid(),
        'mime' => $file->getMimeType(),
      ];

      foreach ($value as $property_name => $property) {
        if (!in_array($property_name, $allow_props) && $property) {
          continue;
        }

        $item_value = $this->serializer->normalize($property, $format, $context);

        if ($field_config && isset($field_config[$property_name])) {
          if (intval($field_config[$property_name]['exclude'])) {
            continue;
          }

          if (intval($field_config[$property_name]['hide_empty'])) {
            if ($item_value === NULL || empty($item_value)) {
              continue;
            }
          }

          if ($field_config[$property_name]['fields'][$property_name]['custom_label']) {
            $property_name = $field_config[$property_name]['fields'][$property_name]['custom_label'];
          }
        }

        $output[$property_name] = $item_value;
      }

      $output['styles'] = [
        'original' => [
          'url' => file_create_url($file_uri),
          'width' => (int) $value['width'],
          'height' => (int) $value['height'],
        ],
      ];

      // if ($output['mime'] === 'image/svg+xml') {
      //   return $output;
      // }

      $styles = ImageStyle::loadMultiple();

      foreach ($styles as $key => $style) {
        $dimensions = [
          'width' => (int) $object->width,
          'height' => (int) $object->height,
        ];

        $style->transformDimensions($dimensions, $file_uri);

        $output['styles'][$key] = [
          'url' => $style->buildUrl($file_uri),
        ];

        if (!empty($dimensions['height']) && !empty($dimensions['width'])) {
          $output['styles'][$key]['height'] = (int) $dimensions['height'];
          $output['styles'][$key]['width'] = (int) $dimensions['width'];
        }
      }

      switch ($image_format) {
        case 'meta_multiple_meta':
          foreach ($output['styles'] as $key => $style) {
            if (isset($field_config['styles'][$key]) && !$field_config['styles'][$key]) {
              unset($output['styles'][$key]);
            }
          }
          break;

        case 'meta_single_meta':

          break;

        case 'meta_multiple':

          break;

        case 'meta_single':

          break;

        case 'multiple_meta':

          break;

        case 'single_meta':

          break;

        case 'multiple':
          if (isset($field_config['styles'])) {

          }
          break;

        case 'single':
          if (isset($field_config['style']) && !empty($field_config['style'])) {
            if (isset($output['styles'][$field_config['style']])) {
              return $output['styles'][$field_config['style']]['url'];
            }
            else {
              return $output['styles']['original']['url'];
            }
          }
          break;

      }
    }

    return $output;
  }
}
