<?php

namespace Drupal\smart_date;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\smart_date\Entity\SmartDateFormatInterface;
use DateTimeZone;

/**
 * Provides friendly methods for smart date range.
 */
trait SmartDateTrait {

  /**
   * The parent entity on which the dates exist.
   *
   * @var mixed
   */
  protected $entity;

  /**
   * The configuration, particularly for the augmenters.
   *
   * @var array
   */
  protected $sharedSettings = [];

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode, $format = '') {
    $field_type = 'smartdate';
    if (property_exists($this, 'fieldDefinition') && $this->fieldDefinition) {
      $field_type = $this->fieldDefinition->getType();
    }
    $elements = [];
    // @todo intelligent switching between retrieval methods.
    // Look for a defined format and use it if specified.
    $format_label = $format ?: $this->getSetting('format');
    if ($format_label) {
      $entity_storage_manager = \Drupal::entityTypeManager()
        ->getStorage('smart_date_format');
      $format = $entity_storage_manager->load($format_label);
      $settings = $format->getOptions();
    }
    else {
      $settings = [
        'separator' => $this->getSetting('separator'),
        'join' => $this->getSetting('join'),
        'time_format' => $this->getSetting('time_format'),
        'time_hour_format' => $this->getSetting('time_hour_format'),
        'date_format' => $this->getSetting('date_format'),
        'date_first' => $this->getSetting('date_first'),
        'ampm_reduce' => $this->getSetting('ampm_reduce'),
        'site_time_toggle' => $this->getSetting('site_time_toggle'),
        'allday_label' => $this->getSetting('allday_label'),
      ];
    }
    $timezone_override = $this->getSetting('timezone_override') ?: NULL;
    $add_classes = $this->getSetting('add_classes');
    $time_wrapper = $this->getSetting('time_wrapper');
    $localize = $this->getSetting('localize');
    $parts = $this->getSetting('parts') ?? [
      'start' => 'start',
      'end' => 'end',
      'duration' => 0,
    ];

    // Field settings may not come back as key/value pairs for the parts.
    // Normalize the array to match the expected structure.
    foreach ($parts as $key => $part) {
      if ((bool) $part && $key != $part) {
        $parts[$part] = $part;
        unset($parts[$key]);
      }
    }
    foreach (['start', 'end', 'duration'] as $key) {
      if (!isset($parts[$key])) {
        $parts[$key] = 0;
      }
    }

    $settings['duration'] = $this->getSetting('duration') ?? [
      'separator' => ' | ',
      'unit' => '',
    ];

    $augmenters = $this->initializeAugmenters();
    if ($augmenters) {
      $this->entity = $items->getEntity();
      if (!empty($this->entity->in_preview)) {
        $augmenters = [];
      }
    }

    foreach ($items as $delta => $item) {
      if ($field_type == 'smartdate') {
        if (empty($item->value) || empty($item->end_value)) {
          continue;
        }
        $start_ts = $item->value;
        $end_ts = $item->end_value;
      }
      elseif ($field_type == 'daterange') {
        // Start and end dates are optional, but one of them is required
        // to display anything regardless of what the field thinks.
        if ($item->isEmpty() || (empty($item->start_date) && empty($item->end_date))) {
          continue;
        }
        elseif (empty($item->start_date)) {
          $start_ts = $end_ts = $item->end_date->getTimestamp();
        }
        elseif (empty($item->end_date)) {
          $start_ts = $end_ts = $item->start_date->getTimestamp();
        }
        else {
          $start_ts = $item->start_date->getTimestamp();
          $end_ts = $item->end_date->getTimestamp();
        }
      }
      elseif ($field_type == 'datetime') {
        if (empty($item->date)) {
          continue;
        }
        $start_ts = $end_ts = $item->date->getTimestamp();
      }
      elseif ($field_type == 'timestamp' || $field_type == 'published_at') {
        if (empty($item->value)) {
          continue;
        }
        $start_ts = $end_ts = $item->value;
      }
      else {
        // Not sure how to handle anything else, so return an empty set.
        return $elements;
      }
      $timezone = $item->timezone ? $item->timezone : $timezone_override;
      // Do an all day check before manipulating the range.
      if (static::isAllDay($start_ts, $end_ts)) {
        $all_day = TRUE;
      }
      else {
        $all_day = FALSE;
      }
      // If necessary, format the duration before altering the times.
      $duration_output = '';
      if ($parts['duration']) {
        $duration_output = $this->formatDuration($start_ts, $end_ts, $settings, $timezone);
      }
      // If only one of start and end are displayed, alter accordingly.
      if ($parts['start'] XOR $parts['end']) {
        if (in_array('start', $parts)) {
          $end_ts = $start_ts;
        }
        else {
          $start_ts = $end_ts;
        }
      }
      if ($parts['start'] || $parts['end']) {
        $elements[$delta] = static::formatSmartDate($start_ts, $end_ts, $settings, $timezone);
      }
      if ($duration_output) {
        // Fix all day events when showing duration.
        if ($all_day && $elements[$delta]['start']) {
          unset($elements[$delta]['start']['join']);
          unset($elements[$delta]['start']['time']);
        }
        if ($elements[$delta]) {
          $elements[$delta]['spacer'] = ['#markup' => $settings['duration']['separator'] ?? ''];
        }
        $elements[$delta]['duration'] = ['#markup' => $duration_output];
      }
      if ($add_classes) {
        $this->addRangeClasses($elements[$delta]);
      }
      if ($time_wrapper) {
        $this->addTimeWrapper($elements[$delta], $start_ts, $end_ts, $timezone, $add_classes, $localize);
      }
      // Attach the timestamps in case they're needed for later processing.
      $elements[$delta]['#value'] = $start_ts;
      $elements[$delta]['#end_value'] = $end_ts;
      // Get the user/site timezone for comparison.
      $user = \Drupal::currentUser();
      $user_tz = $user->getTimeZone();
      if (!static::isAllDay($start_ts, $end_ts, $timezone) && $settings['site_time_toggle'] && $timezone && $timezone != $user_tz) {
        // Uses a custom timezone, so append time in default timezone.
        $no_date_format = $settings;
        $default_date = \Drupal::service('date.formatter')->format($start_ts, '', $settings['date_format'], $timezone);
        $user_date = \Drupal::service('date.formatter')->format($start_ts, '', $settings['date_format'], $user_tz);
        // If the date is the same in both timezones, only display it once.
        if ($default_date == $user_date) {
          $no_date_format['date_format'] = '';
        }
        $site_time = static::formatSmartDate($start_ts, $end_ts, $no_date_format, $user_tz);
        // Only process further if a value is returned.
        if ($site_time) {
          $event_time = static::formatSmartDate($start_ts, $end_ts, $no_date_format, $timezone);
          // Only append if displayed time will be different.
          if ($site_time != $event_time) {
            $site_time['#prefix'] = ' (';
            $site_time['#suffix'] = ')';
            $elements[$delta]['site_time'] = $site_time;
          }
        }
      }

      if (!empty($item->_attributes)) {
        $elements[$delta]['#attributes'] += $item->_attributes;
        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }

      if ($augmenters) {
        $this->augmentOutput($elements[$delta], $augmenters, $item->value, $item->end_value, $timezone, $delta);
      }
    }

    // If specified, sort based on start, end times.
    if ($this->getSetting('force_chronological')) {
      $elements = smart_date_array_orderby($elements, '#value', SORT_ASC, '#end_value', SORT_ASC);
    }

    return $elements;
  }

  /**
   * Explicitly declare support for the Date Augmenter API.
   *
   * @return bool
   *   Return TRUE to declare support.
   */
  public function supportsDateAugmenter() {
    // Could have conditional logic here.
    return TRUE;
  }

  /**
   * Add spans provides classes to allow the dates and times to be styled.
   *
   * @param array $instance
   *   The render array of the formatted date range.
   */
  protected static function addRangeClasses(array &$instance) {
    // Array to define where wrapper parts should be skipped, for a range.
    $skip = [];
    // If a time range within a day, make a single wrapper around the times.
    if ((isset($instance['start']['date']) xor isset($instance['end']['date'])) && isset($instance['start']['time'], $instance['end']['time'])) {
      $skip['start']['time']['#suffix'] = TRUE;
      $skip['end']['time']['#prefix'] = TRUE;
    }
    // For a date only range, make a single wrapper.
    elseif (isset($instance['start']['date'], $instance['end']['date']) && (!isset($instance['start']['time']) || !isset($instance['end']['time']))) {
      $skip['start']['date']['#suffix'] = TRUE;
      $skip['end']['date']['#prefix'] = TRUE;
    }
    // Wrap all parts by default.
    foreach (['start', 'end'] as $part) {
      foreach (['date', 'time'] as $subpart) {
        if (isset($instance[$part][$subpart]) && $instance[$part][$subpart]) {
          if (!isset($skip[$part][$subpart]['#prefix'])) {
            $instance[$part][$subpart]['#prefix'] = '<span class="smart-date--' . $subpart . '">';
          }
          if (!isset($skip[$part][$subpart]['#suffix'])) {
            $instance[$part][$subpart]['#suffix'] = '</span>';
          }
        }
      }
    }
  }

  /**
   * Add spans provides classes to allow the dates and times to be styled.
   *
   * @param array $instance
   *   The render array of the formatted date range.
   * @param object $start_ts
   *   A timestamp.
   * @param object $end_ts
   *   A timestamp.
   * @param string|null $timezone
   *   An optional timezone override.
   * @param bool $add_classes
   *   Whether or not the field is also adding class wrappers.
   * @param bool $localize
   *   Whether or not to append a Javascript library to localize times.
   */
  protected static function addTimeWrapper(array &$instance, $start_ts, $end_ts, $timezone = NULL, $add_classes = FALSE, $localize = FALSE) {
    $times = [
      'start' => $start_ts,
      'end' => $end_ts,
    ];
    // Only add the time wrappers inside if there is an incomplete range part.
    if ($localize || (isset($instance['start']['date']) xor isset($instance['start']['time'])) || (isset($instance['end']['date']) xor isset($instance['end']['time']))) {
      $inner_wrappers = TRUE;
    }
    else {
      $inner_wrappers = FALSE;
    }
    foreach (['start', 'end'] as $part) {
      if (!isset($instance[$part])) {
        continue;
      }
      if (static::isAllDay($start_ts, $end_ts, $timezone)) {
        $format = 'Y-m-d';
      }
      else {
        $format = 'c';
      }
      $datetime = \Drupal::service('date.formatter')->format($times[$part], 'custom', $format);
      if ($localize || ($add_classes && $inner_wrappers)) {
        // If wrappers for classes have also been added, we need separate
        // time elements for the date and time, if set.
        foreach (['date', 'time'] as $subpart) {
          if (isset($instance[$part][$subpart]) && $instance[$part][$subpart]) {
            $current_contents = $instance[$part][$subpart];
            unset($current_contents['#prefix']);
            unset($current_contents['#suffix']);
            $prefix = isset($instance[$part][$subpart]['#prefix']) ? $instance[$part][$subpart]['#prefix'] : NULL;
            $suffix = isset($instance[$part][$subpart]['#suffix']) ? $instance[$part][$subpart]['#suffix'] : NULL;
            $instance[$part][$subpart] = [
              '#theme' => 'time',
              '#attributes' => ['datetime' => $datetime],
              '#text' => $current_contents,
              '#prefix' => $prefix,
              '#suffix' => $suffix,
            ];
            // If configured, set up for localization, but not if all day.
            if ($localize && $format == 'c') {
              if (isset($instance[$part][$subpart]['#text']['#format'])) {
                $instance[$part][$subpart]['#attributes']['data-format'] = $instance[$part][$subpart]['#text']['#format']['#markup'];
              }
              $tz_string = $timezone ?? date_default_timezone_get();
              if ($tz_string) {
                // $utc = new DateTimeZone('UTC');
                $tzObject = new DateTimeZone($tz_string);
                $date = new \DateTime('now', new \DateTimeZone('UTC'));
                // Set a data attribute using offset as used by Javascript.
                $instance[$part][$subpart]['#attributes']['data-tzoffset'] = (0 - $tzObject->getOffset($date)) / 60;
              }
              $instance[$part][$subpart]['#attached']['library'][] = 'smart_date/localize';
              $instance[$part][$subpart]['#attributes']['class'][] = 'smart-date--localize';
            }
          }
        }
      }
      else {
        $current_contents = $instance[$part];
        $instance[$part] = [
          '#theme' => 'time',
          '#attributes' => ['datetime' => $datetime],
          '#text' => $current_contents,
        ];
      }
    }
    if (!empty($instance['duration'])) {
      // For the sake of finding differences, "fix" all day events.
      if (static::isAllDay($start_ts, $end_ts, $timezone)) {
        $adjusted_end = $end_ts + 60;
      }
      else {
        $adjusted_end = $end_ts;
      }
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $diff = \Drupal::service('date.formatter')->formatDiff($start_ts, $adjusted_end, [
        'strict' => FALSE,
        'language' => $language,
      ]);
      $current_contents = $instance['duration'];
      $instance['duration'] = [
        '#theme' => 'time',
        '#attributes' => ['datetime' => static::formatDurationTime($diff)],
        '#text' => $current_contents,
      ];
    }
  }

  /**
   * Creates a formatted date value as a string.
   *
   * @param object $start_ts
   *   A timestamp.
   * @param object $end_ts
   *   A timestamp.
   * @param mixed $settings
   *   The formatter settings.
   * @param string|null $timezone
   *   An optional timezone override.
   * @param string $return_type
   *   An optional parameter to force the return value to be a string.
   *
   * @return string|array
   *   A formatted date range using the chosen format.
   */
  public static function formatSmartDate($start_ts, $end_ts, mixed $settings = [], $timezone = NULL, $return_type = '') {
    $settings = static::normalizeSettings($settings);
    $range = [];

    // Don't need to reduce dates unless conditions are met.
    $date_reduce = FALSE;
    // Ensure that empty timezones are NULL to avoid errors.
    if (empty($timezone)) {
      $timezone = NULL;
    }
    // If no formatting parameters provided, use the default settings.
    if (!$settings) {
      $settings = static::loadSmartDateFormat('default');
      if (!$settings) {
        return FALSE;
      }
    }
    // Apply date format from the display config.
    if ($settings['date_format']) {
      $range['start']['date'] = [
        'value' => \Drupal::service('date.formatter')->format($start_ts, '', $settings['date_format'], $timezone),
        '#format' => $settings['date_format'],
      ];
      $range['end']['date'] = [
        'value' => \Drupal::service('date.formatter')->format($end_ts, '', $settings['date_format'], $timezone),
        '#format' => $settings['date_format'],
      ];

      if ($range['start']['date']['value'] == $range['end']['date']['value']) {
        if ($settings['date_first']) {
          unset($range['end']['date']);
        }
        else {
          unset($range['start']['date']);
        }
      }
      else {
        // If a date range and reduce is set, reduce duplication in the dates.
        $date_reduce = $settings['ampm_reduce'];
        // Don't reduce am/pm if spanning more than one day.
        $settings['ampm_reduce'] = FALSE;
      }
    }
    // If not rendering times, we can stop here.
    if (!$settings['time_format']) {
      if ($date_reduce) {
        // Reduce duplication in date only range.
        $range = static::rangeDateReduce($range, $settings, $start_ts, $end_ts, $timezone);
      }
      return static::rangeFormat($range, $settings, $return_type);
    }
    if ($timezone) {
      $settings['timezone_reset'] = date_default_timezone_get();
      date_default_timezone_set($timezone);
      $tz_check = $timezone;
    }
    else {
      // If no timezone set, make sure we use site default for check.
      $tz_check = \Drupal::config('system.date')->get('timezone.default');
    }
    $temp_start = date('H:i', $start_ts);
    $temp_end = date('H:i', $end_ts);

    // If one of the dates are missing, they must have been the same.
    if (!isset($range['start']['date']) || !isset($range['end']['date'])) {

      // Check for zero duration.
      if ($temp_start == $temp_end) {
        if ($settings['date_first']) {
          $range['start']['time'] = static::timeFormat($end_ts, $settings, $timezone);
        }
        else {
          $range['end']['time'] = static::timeFormat($end_ts, $settings, $timezone);
        }
        return static::rangeFormat($range, $settings, $return_type);
      }

      // If the conditions that make this necessary aren't met, set to skip.
      if (!$settings['ampm_reduce'] || (date('a', $start_ts) != date('a', $end_ts))) {
        $settings['ampm_reduce'] = FALSE;
      }
    }
    // Check for an all-day range.
    if (static::isAllDay($start_ts, $end_ts, $tz_check)) {
      if ($settings['allday_label']) {
        if (($settings['date_first'] && isset($range['end']['date'])) || empty($range['start']['date'])) {
          $range['end']['time'] = $settings['allday_label'];
        }
        else {
          $range['start']['time'] = $settings['allday_label'];
        }
      }
      if ($date_reduce) {
        // Reduce duplication in date only range.
        $range = static::rangeDateReduce($range, $settings, $start_ts, $end_ts, $timezone);
      }
      return static::rangeFormat($range, $settings, $return_type);
    }

    $range['start']['time'] = static::timeFormat($start_ts, $settings, $timezone, TRUE);
    $range['end']['time'] = static::timeFormat($end_ts, $settings, $timezone);
    return static::rangeFormat($range, $settings, $return_type);
  }

  /**
   * Removes date tokens from format settings.
   *
   * @param array $settings
   *   The formatter settings.
   *
   * @return array
   *   The settings with date output stripped.
   */
  public static function settingsFormatNoDate(array $settings = []) {
    if (isset($settings['date_format'])) {
      $settings['date_format'] = '';
    }
    return $settings;
  }

  /**
   * Removes time tokens from format settings.
   *
   * @param array $settings
   *   The formatter settings.
   *
   * @return array
   *   The settings with time output stripped.
   */
  public static function settingsFormatNoTime(array $settings = []) {
    if (isset($settings['time_format'])) {
      $settings['time_format'] = '';
    }
    return $settings;
  }

  /**
   * Removes timezone tokens from time settings.
   *
   * @param array $settings
   *   The formatter settings.
   *
   * @return array
   *   The settings with timezone output stripped.
   */
  public static function settingsFormatNoTz(array $settings = []) {
    if (isset($settings['time_format'])) {
      $settings['time_format'] = preg_replace('/\s*(?<![\\\\])[eOPTZ]/i', '', $settings['time_format']);
    }
    if (isset($settings['time_hour_format'])) {
      $settings['time_hour_format'] = preg_replace('/\s*(?<![\\\\])[eOPTZ]/i', '', $settings['time_hour_format']);
    }
    return $settings;
  }

  /**
   * Load a Smart Date Format from a format name.
   *
   * @param string $formatName
   *   The machine name of a Smart Date Format.
   *
   * @return null|array
   *   An array of the format's options.
   */
  public static function loadSmartDateFormat($formatName) {
    $format = NULL;

    $loadedFormat = \Drupal::entityTypeManager()
      ->getStorage('smart_date_format')
      ->load($formatName);

    if ($loadedFormat instanceof SmartDateFormatInterface) {
      $format = $loadedFormat->getOptions();
    }

    return $format;
  }

  /**
   * Reduce duplication in a provided date range.
   *
   * @param array $range
   *   The date/time range to format.
   * @param mixed $settings
   *   The date/time range to format.
   * @param object $start_ts
   *   A timestamp.
   * @param object $end_ts
   *   A timestamp.
   * @param string|null $timezone
   *   Timezone.
   *
   * @return string|array
   *   The range, with duplicate elements removed.
   */
  protected static function rangeDateReduce(array $range, mixed $settings, $start_ts, $end_ts, $timezone = NULL) {
    $settings = static::normalizeSettings($settings);
    // First attempt has the following limitations, to reduce complexity:
    // * Day ranges only work either d or j, and no other day tokens.
    // * Not able to handle S token unless adjacent to day.
    // * Month, day ranges only work if year at start or end.
    $start = getdate($start_ts);
    $end = getdate($end_ts);
    // If an empty date format, nothing to do.
    if (empty($settings['date_format'])) {
      return $range;
    }
    $range['start']['date']['#format'] = $settings['date_format'];
    $range['end']['date']['#format'] = $settings['date_format'];
    // If the years are different, no deduplication necessary.
    if ($start['year'] != $end['year']) {
      return $range;
    }
    $valid_days = [];
    $invalid_days = [];
    // Populate start and end format variables.
    $start_format = $end_format = $settings['date_format'];
    // Check for workable day tokens.
    preg_match_all('/(?<!\\\)[dj]/', $settings['date_format'], $valid_days, PREG_OFFSET_CAPTURE);
    // Check for challenging day tokens.
    preg_match_all('/(?<!\\\)[DNlwz]/', $settings['date_format'], $invalid_days, PREG_OFFSET_CAPTURE);
    // If specific conditions are met format as a range within the month.
    if ($start['month'] == $end['month'] && count($valid_days[0]) == 1 && count($invalid_days[0]) == 0) {
      // Split the date string at the valid day token.
      $day_loc = $valid_days[0][0][1];
      // Don't remove the S token from the start if present.
      if ($s_loc = strpos($settings['date_format'], 'S', $day_loc)) {
        $offset = 1 + $s_loc - $day_loc;
      }
      // Preserve the period after the date for German formats.
      elseif ($p_loc = strpos($settings['date_format'], '.', $day_loc)) {
        $offset = 1 + $p_loc - $day_loc;
      }
      else {
        $offset = 1;
      }
      $start_format = substr($settings['date_format'], 0, $day_loc + $offset);
      $end_format = substr($settings['date_format'], $day_loc);
    }
    else {
      // Only remaining possibility is to deduplicate the year.
      // NOTE: Our code only works with a 4 digit year format.
      if (strpos($settings['date_format'], 'Y') === 0) {
        $year_pos = 0;
      }
      elseif (strpos($settings['date_format'], 'Y') == (strlen($settings['date_format']) - 1)) {
        $year_pos = -1;
      }
      else {
        // Too complicated if year is in the middle.
        return $range;
      }
      $valid_tokens = [];
      // Check for workable day or month tokens.
      preg_match_all('/(?<!\\\)[djDNlwzSFmMn]/', $settings['date_format'], $valid_tokens, PREG_OFFSET_CAPTURE);
      if (!$valid_tokens) {
        return $range;
      }
      if ($year_pos == 0) {
        // Year is at the beginning, so change the end to start at the
        // first valid token after it.
        $first_token = $valid_tokens[0][0];
        $end_format = substr($settings['date_format'], $first_token[1]);
      }
      else {
        $last_token = array_pop($valid_tokens[0]);
        $start_format = substr($settings['date_format'], 0, $last_token[1] + 1);
      }
    }
    $range['start']['date'] = [
      'value' => \Drupal::service('date.formatter')->format($start_ts, '', $start_format, $timezone),
      '#format' => $start_format,
    ];
    $range['end']['date'] = [
      'value' => \Drupal::service('date.formatter')->format($end_ts, '', $end_format, $timezone),
      '#format' => $end_format,
    ];
    return $range;
  }

  /**
   * Format a provided range, using provided settings.
   *
   * @param array $range
   *   The date/time range to format.
   * @param mixed $settings
   *   The date/time range to format.
   * @param string $return_type
   *   An option to specify that a string should be returned. If left empty,
   *   a render array will be returned instead.
   *
   * @return string|array
   *   The formatted range.
   */
  protected static function rangeFormat(array $range, mixed $settings, $return_type = '') {
    $settings = static::normalizeSettings($settings);
    // If a string is requested, return that.
    if ($return_type == 'string') {
      $pieces = [];
      foreach ($range as $key => $parts) {
        if ($parts) {
          if (!$settings['date_first']) {
            // Time should be first so reverse the array.
            krsort($parts);
          }
          foreach ($parts as $pkey => $part) {
            if (isset($part['value'])) {
              $parts[$pkey] = $part['value'];
            }
          }
          $pieces[] = implode($settings['join'], $parts);
        }
      }
      return implode($settings['separator'], $pieces);
    }
    // Otherwise, return a render array so it can be altered.
    foreach ($range as $key => &$parts) {
      if ($parts && is_array($parts) && count($parts) > 1) {
        $parts['join'] = $settings['join'];
        if ($settings['date_first']) {
          // Date should be first so sort the array.
          ksort($parts);
        }
        else {
          // Time should be first so reverse the array.
          krsort($parts);
        }
      }
      elseif (!$parts) {
        unset($range[$key]);
      }
    }
    if (count($range) > 1) {
      $range['separator'] = $settings['separator'];
      krsort($range);
    }
    // Otherwise, return a nested array.
    $output = static::arrayToRender($range);
    $output['#attributes']['class'] = ['smart_date_range'];
    // If a timezone was forced, reset to the default.
    if (!empty($settings['timezone_reset'])) {
      date_default_timezone_set($settings['timezone_reset']);
    }
    return $output;
  }

  /**
   * Helper function to turn a simple, nested array into a render array.
   *
   * @param array $array
   *   An array, potentially nested, to be converted.
   *
   * @return array
   *   The nested render array.
   */
  protected static function arrayToRender(array $array) {
    if (!is_array($array)) {
      return FALSE;
    }
    $output = [];
    // Iterate though the array.
    foreach ($array as $key => $child) {
      $child == array_pop($array);
      if (is_array($child)) {
        $output[$key] = static::arrayToRender($child);
      }
      else {
        $output[$key] = [
          '#markup' => $child,
        ];
      }
    }
    return $output;
  }

  /**
   * Helper function to apply time formats.
   *
   * @param int $time
   *   The timestamp to format.
   * @param mixed $settings
   *   The settings that will be used for formatting.
   * @param string|null $timezone
   *   An optional timezone override.
   * @param bool $is_start
   *   If this is the start time in a range, it requires special treatment.
   *
   * @return array
   *   An array containing the formatted time, and the format applied.
   */
  protected static function timeFormat($time, mixed $settings, $timezone = NULL, $is_start = FALSE) {
    $settings = static::normalizeSettings($settings);
    $format = $settings['time_format'];
    if (!empty($settings['time_hour_format']) && date('i', $time) == '00') {
      $format = $settings['time_hour_format'];
    }
    if ($is_start) {
      if ($settings['ampm_reduce']) {
        // Remove am/pm if configured to.
        $format = preg_replace('/\s*(?<![\\\\])a/i', '', $format);
      }
      // Remove the timezone at the start of a time range.
      $format = preg_replace('/\s*(?<![\\\\])[eOPTZ]/i', '', $format);
    }
    return [
      'value' => \Drupal::service('date.formatter')->format($time, '', $format, $timezone),
      '#format' => $format,
    ];
  }

  /**
   * Evaluates whether or not a provided range is "all day".
   *
   * @param object $start_ts
   *   A timestamp.
   * @param object $end_ts
   *   A timestamp.
   * @param string|null $timezone
   *   An optional timezone override.
   *
   * @return bool
   *   Whether or not the timestamps are considered all day by Smart Date.
   */
  public static function isAllDay($start_ts, $end_ts, $timezone = NULL) {
    if ($timezone) {
      if ($timezone instanceof DateTimeZone) {
        // If provided as an object, convert to a string.
        $timezone = $timezone->getName();
      }
      // Apply a specific timezone provided.
      $default_tz = date_default_timezone_get();
      date_default_timezone_set($timezone);
    }
    // Format timestamps to predictable format for comparison.
    $temp_start = date('H:i', $start_ts);
    $temp_end = date('H:i', $end_ts);
    if ($timezone) {
      // Revert to previous timezone.
      date_default_timezone_set($default_tz);
    }
    if ($temp_start == '00:00' && $temp_end == '23:59') {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Use provided configuration to retrieve a list of date augmenters.
   *
   * @param array $keys
   *   Optional array to allow multiple sets of augmenter configurations.
   *
   * @return array
   *   An array of the available augmenters.
   */
  protected function initializeAugmenters(array $keys = []) {
    if (empty(\Drupal::hasService('plugin.manager.dateaugmenter'))) {
      return [];
    }
    $config = $this->getThirdPartySettings('date_augmenter');
    $this->sharedSettings = $config;
    $dateAugmenterManager = \Drupal::service('plugin.manager.dateaugmenter');
    // @todo Support custom entities.
    if ($keys) {
      $augmenters = [];
      foreach ($keys as $key) {
        $key_config = $config[$key] ?? NULL;
        $augmenters[$key] = $dateAugmenterManager->getActivePlugins($key_config);
      }
    }
    else {
      $augmenters = $dateAugmenterManager->getActivePlugins($config);
    }
    return $augmenters;
  }

  /**
   * Apply any configured augmenters.
   *
   * @param array $output
   *   Render array of output.
   * @param array $augmenters
   *   The augmenters that have been configured.
   * @param int $start_ts
   *   The start of the date range.
   * @param int $end_ts
   *   The end of the date range.
   * @param string $timezone
   *   The timezone to use.
   * @param int $delta
   *   The field delta being formatted.
   * @param string $type
   *   The set of configuration to use.
   * @param string $repeats
   *   An optional RRULE string containing recurrence details.
   * @param string $ends
   *   An optional timestamp to specify the end of the last instance.
   */
  protected function augmentOutput(array &$output, array $augmenters, $start_ts, $end_ts, $timezone, $delta, $type = '', $repeats = '', $ends = '') {
    if (!$augmenters) {
      return;
    }

    foreach ($augmenters as $augmenter_id => $augmenter) {
      if (!empty($type)) {
        $settings = $this->sharedSettings[$type]['settings'][$augmenter_id] ?? [];
      }
      else {
        $settings = $this->sharedSettings['settings'][$augmenter_id] ?? [];
      }

      $augmenter->augmentOutput(
        $output,
        DrupalDateTime::createFromTimestamp($start_ts),
        DrupalDateTime::createFromTimestamp($end_ts),
        [
          'timezone' => $timezone,
          'allday' => static::isAllDay($start_ts, $end_ts, $timezone),
          'entity' => $this->entity,
          'settings' => $settings,
          'delta' => $delta,
          'formatter' => $this,
          'repeats' => $repeats,
          'ends' => empty($ends) ? $ends : DrupalDateTime::createFromTimestamp($ends),
          'field_name' => $this->fieldDefinition->getName(),
        ]
      );
    }
  }

  /**
   * Format the duration according to the configuration.
   *
   * @param int $start_ts
   *   The start of the date range.
   * @param int $end_ts
   *   The end of the date range.
   * @param mixed $settings
   *   The settings that will be used for formatting.
   * @param string $timezone
   *   The timezone to use.
   *
   * @return string
   *   The formatted duration string.
   */
  protected function formatDuration($start_ts, $end_ts, $settings, $timezone) {
    $settings = $this->normalizeSettings($settings);
    if (static::isAllDay($start_ts, $end_ts, $timezone)) {
      return $settings['allday_label'];
    }
    if (empty($unit = $settings['duration']['unit'] ?? '')) {
      return \Drupal::service('date.formatter')->formatDiff($start_ts, $end_ts);
    }

    // Nonstadard duration formatting configured, make our own diff obj.
    $date_time_from = new \DateTime();
    $date_time_from->setTimestamp($start_ts);
    $date_time_to = new \DateTime();
    $date_time_to->setTimestamp($end_ts);
    $interval = $date_time_to->diff($date_time_from);
    if ($unit == 'h') {
      $decimals = $this->getSetting('decimals');
      $duration_output = ($interval->h + round($interval->i / 60, $decimals));
    }
    else {
      $duration_output = ($interval->h * 60) + $interval->i;
    }
    $duration_output .= $settings['duration']['suffix'] ?? '';
    return $duration_output;
  }

  /**
   * Format the string to be used as the datetime value.
   *
   * @param string $string
   *   The string returned by DateFormatter::formatDiff.
   *
   * @return string
   *   The formatted duration string.
   */
  protected static function formatDurationTime($string) {
    if (empty($string)) {
      return '';
    }
    $abbr_string = 'P';
    $intervals = [
      'Y' => 'year',
      'D' => 'day',
      'H' => 'hour',
      'M' => 'minute',
    ];
    foreach ($intervals as $key => $match_string) {
      $pattern = '/(\d+) ' . $match_string . '(s)?/i';
      preg_match($pattern, $string, $matches);
      if ($matches) {
        $abbr_string .= $matches[1] . $key;
      }
    }
    if (strlen($abbr_string) == 1) {
      $abbr_string = '';
    }

    return $abbr_string;
  }

  /**
   * If $settings has been provided as a string.
   */
  public static function normalizeSettings($settings) {
    if (is_array($settings) && !empty($settings)) {
      return $settings;
    }
    elseif (empty($settings)) {
      $settings = 'default';
    }
    if (is_string($settings)) {
      $settings = static::loadSmartDateFormat($settings);
    }
    return $settings;
  }

}
