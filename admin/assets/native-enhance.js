/**
 * Progressive enhancement for native WordPress admin pages that benefit from
 * Aurora's boxed "card" layout but ship as flat sibling markup.
 *
 * Currently targets the Updates page (update-core.php): it groups each
 * section (Current version, Plugins, Themes) into a .aurora-native-card div
 * so the dark stylesheet can give them the same surface as the dashboard
 * cards. Purely visual and reversible — no native behaviour is altered.
 */
(function () {
  'use strict';

  function enhanceUpdates() {
    var wrap = document.querySelector('#wpbody-content .wrap');
    if (!wrap || wrap.querySelector('.aurora-native-card')) {
      return;
    }

    var children = Array.prototype.slice.call(wrap.children);

    // A new card starts at each major section heading. The "response" h2
    // ("You have the latest version") belongs inside the version section,
    // so it is not treated as a boundary.
    var isBoundary = function (el) {
      return el.tagName === 'H2' && !el.classList.contains('response');
    };

    var groups = [];
    var current = null;

    children.forEach(function (el) {
      if (el.tagName === 'H1') {
        return; // page title stays outside the cards
      }
      if (!current && !isBoundary(el)) {
        return; // intro paragraph(s) before the first section stay in place
      }
      if (isBoundary(el)) {
        current = [];
        groups.push(current);
      }
      if (current) {
        current.push(el);
      }
    });

    groups.forEach(function (group) {
      if (!group.length) {
        return;
      }
      var card = document.createElement('div');
      card.className = 'aurora-native-card';
      group[0].parentNode.insertBefore(card, group[0]);
      group.forEach(function (el) {
        card.appendChild(el);
      });
    });
  }

  function run() {
    if (document.body.classList.contains('update-core-php')) {
      enhanceUpdates();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();
