<?php

// Should use placeholder in t() instead.
$x = t('Test') . ': ' . $variable;

// Concatenating markup is fine.
$x = '<p>' . t('Test') . '</p>';

// Concatenating some symbols is fine.
$x = '(' . t('Test') . ')';
$x = '[' . t('Test') . ']';
$x = '- ' . t('Test') . ' -';
$x = '<' . t('Test') . '>';
$x = '«' . t('Test') . '»';

// Concatenating markup with text in it is not fine.
$x = '<p>' . t('Test') . '</p><p>More text.</p>';

// Concatenating symbols with text in it is not fine.
$x = t('Test') . ' (disabled)';

class Test {

  public function doSomething() {
    return $this->t('This string should avoid backslashes for \'');
  }

  public function doSomethingElse() {
    return new TranslatableMarkup('Do not ' . 'concatenate');
  }

  public function doSomethingMore() {
    return new TranslationWrapper(' Do not start with a space');
  }

}

// CiviCRM ts() and the civix E::ts() helper follow the same rules.
$x = ts("Saved $name");
$x = ts('Saved' . ' %1', [1 => $name]);
$x = CRM_Foo_ExtensionUtil::ts(' Saved');
$x = ts('Saved \'%1\'', [1 => $name]);
$x = ts('Saved %1', [1 => $name]);
$x = E::ts("Saved '%1'", [1 => $name]);
$x = E::ts('receipt') . '.pdf';

class CRM_Foo_ExtensionUtil {

  public static function ts($text, $params = []) {
    return ts($text, $params);
  }

  public function other() {
    return self::ts($this->label) . $this->ts($this->label);
  }

}

// ts() keeps typography inside the string.
$x = ts('Selected Option') . ': ' . $option;
$x = ts('Renew') . '...';
$x = ts('Contribution') . ' %%StatusMessage%%';
$x = ts('Invalid type') . ': "' . $type . '"';
// ts() embedded in markup, SQL, JavaScript or Smarty, or used as a file name, is fine.
$x = '<a title="' . ts('View Contact') . '" href="' . $url . '">';
$x = 'IF(is_hidden, "' . ts('Search Results') . '", title) AS value';
$x = 'IF(owner_id, "(' . E::ts('by relationship') . ')", owner_id)';
$x = "{if '[parent_id]'}" . ts('Subtype of %1', [1 => '[parent_id:label]']) . "{/if}";
$x = "<a href='#'>" . ts('flip') . "</a>&nbsp;|&nbsp;";
$x = ts('First sentence.') . '\n\n' . ts('Second sentence.');
