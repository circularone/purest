<?php
/**
 * Event subscriber to remove X-Generated by header from rest responses
 */
namespace Drupal\purest\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class RemoveXGeneratorHeaderSubscriber.
 */
class RemoveXGeneratorHeaderSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events['kernel.response'] = ['kernel_response'];

    return $events;
  }

  /**
   * This method is called whenever the kernel.response event is
   * dispatched.
   *
   * Remove X-Generator "Drupal" header from rest responses
   *
   * @param GetResponseEvent $event
   */
  public function kernel_response(Event $event) {
    $response = $event->getResponse();
    $response->headers->remove('X-Generator');
  }

}
