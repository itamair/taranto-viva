/**
 * @file
 * Defines JavaScript behaviors for the graphql compose settings form.
 */

(($, Drupal) => {
  /**
   * Behaviors for summaries for tabs in the graphql compose settings form.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches summary behavior for tabs in the graphql compose settings form.
   */
  Drupal.behaviors.graphqlComposeFormSummaries = {
    attach(context) {
      $('.entity-type-tab', context).drupalSetSummary(
        Drupal.behaviors.graphqlComposeFormSummaries.getMessage,
      );
      $('.entity-bundle-tab', context).drupalSetSummary(
        Drupal.behaviors.graphqlComposeFormSummaries.getMessage,
      );
    },
  };

  /**
   * Get the message for tabs in the graphql compose settings form.
   *
   * @param {Element} element
   *   The element building a summary for.
   *
   * @return {string}
   *   The summary message.
   */
  Drupal.behaviors.graphqlComposeFormSummaries.getMessage = (element) => {
    const enabled = element.querySelectorAll('.entity-bundle-enabled:checked');
    const errors = element.querySelectorAll('.has-error');

    if (errors.length) {
      const span = document.createElement('span');
      span.classList.add('has-nested-errors');
      span.innerText = Drupal.formatPlural(
        errors.length,
        '1 error',
        '@count errors',
      );

      return span.outerHTML;
    }

    return enabled.length ? Drupal.t('Enabled') : '';
  };
})(jQuery, Drupal);
