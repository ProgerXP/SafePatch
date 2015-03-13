<?php
# Make sure this file is stored in UTF-8 (test string: ハロー！).
return array(
  'locale' => 'ru_RU.UTF-8',

  'panel' => array(
    /* -- COMMON -- */

      'datetime' => 'j.m.Y в H:i',
      'date' => 'j.m.Y',
      'time' => 'H:i',

      'by' => '%s, %s',
      'versionDate' => 'v%s, %s',
      'version' => 'v%s',

      'updateViewBtn' => 'Применить',     // for example, a button to apply form filter.
      'resetViewBtn' => 'Сбросить',
      'confirmBtn' => 'Продолжить',       // warning/info message confirmation button.

      'filedropLegend' => 'Перетащите файл внутрь&hellip;',
      'filedropButton' => 'Или нажмите здесь для выбора.',

      'nonExistingPatch' => 'Заплатка %s не существует.',
      'nonExistingPatchApplied' => 'Заплатка %s не существует или он не применён.',

      'requestChangeDenied' => 'У вас нет прав для выполнения этого действия (%s).',
      'cannotLock' => 'Невозможно заблокировать директорию <strong>SafePatch</strong> &mdash; действие прервано.',

      'patchWillAffect' => 'Заплатка %s изменит <strong>$</strong>.',
      'patchWillAffectNum' => '$ файл, ов, , а, ов',
      'patchWontChange' => 'Заплатка %s не изменит ни одного файла.',

    /* -- SYSTEM -- */

      'panelTitle' => 'Управление %sSafePatch%s',
      'panelVersion' => '%sv%.1f%s r%s',
      'panelMotto' => 'Управляемое редактирование программных файлов%sвне зависимости от типа, платформы и языка',

      'panelMenuWiki' => 'Вики',
      'panelMenuBugs' => 'Баги',
      'panelMenuSVN' => 'SVN',

      'exceptionTitle' => 'Произошла ошибка',
      'exception' => '<strong>%s</strong> в <kbd>%s</kbd> на строке <strong>%s</strong> (страница %s):%s',
      'exceptionTrace' => '<h2>Список вызовов</h2>%s',
      'exceptionBug' => 'Если вы думаете, что это ошибка в <strong>SafePatch</strong> &mdash; пожалуйста, %sсообщите нам об этом%s.',

      'invalidReqParams' => 'Неверные параметры запроса для этой страницы (%s).',

      'statsFreshen' => '<strong>Обновлено:</strong> %s.',
      'statsFreshenNever' => 'никогда',
      'statsPatches' => '%s, %s.',
      'statsPatchesApplied' => '$ %sзаплат, ок, ка, ки, ок',
      'statsPatchesAvailable' => '$ доступн, ых, ая, ые, ых',
      'statsHome' => '<strong>%sНачало%s:</strong> %s',
      'statsLastLogin' => '<strong>Посл. вход:</strong> %s %s как %s с %s.',

      '404' => 'Запрошенная страница %s  (%s) не существует.',

      'spBadFilePerms' => 'Файловые права настроены неверно &mdash; возможны проблемы с безопастностью или работоспособностью:',
      'spNonReadable' => '%s &mdash; нужно <strong>только чтение</strong> (текущие права: %s)',
      'spNonWritable' => '%s &mdash; нужно <strong>чтение и запись</strong> (текущие права: %s)',

      'newLogTitle' => '$ в журнале',
      'newLogTitleNum' => '$ сообщени, й, е, я, й',
      'newLogLegend' => 'Больше информации доступно в %sфайле журнала%s.',

    /* -- PAGES -- */

    'patches' => array(
      'pageTitle' => 'Заплатки',
      'appliedTitle' => 'Применённые',
      'availableTitle' => 'Доступные',

      'noPatches' => 'Заплаток пока нет.',

      'uploadTitle' => 'Загрузить новую',
      'uploadApplyBtn' => 'Загрузить &amp; применить',
      'uploadPreviewBtn' => 'Предпросмотр',
      'uploadPreview' => 'Показать перед применением.',

      'effectWildcard' => 'Затронет <em>по крайней мере</em> $ &mdash; %sпоказать%s.',
      'effectWildcardNum' => '$ файл, ов, , а, ов',
      'effect' => 'Затронет $ &mdash; %sпоказать%s.',
      'effectNum' => '$ файл, ов, , а, ов',

      'apply' => 'Применить',
      'missingFreshness' => 'Применена, но время не известно',
      'applied' => 'Применена %s',
      'revert' => 'Откатить',
      'tryReverting' => 'Попробовать откатить',
      'obliterate' => 'Забыть',

      'stateOnly' => 'Файл заплатки %s был удалён.',
    ),

    'freshen' => array(
      'pageTitle' => 'Обновить заплатки',

      'noPatches' => 'В папке %s заплаток нет.',
      'up-to-date' => 'Все заплатки (%s) уже применены.',
      'freshenedPatches' => '<strong>$</strong> были успешно применены:',
      'freshenedPatchesNum' => '$ заплат, ок, ка, ки, ок',
    ),

    'apply' => array(
      'pageTitle' => '&laquo;Пришивание&raquo; заплатки',
      'done' => 'Заплатка %s успешно &laquo;пришита&raquo;.',
      'doublePatching' => 'Попытка применить заплатку %s дважды.',
    ),

    'revert' => array(
      'pageTitle' => 'Откат заплатки',

      'revertingUnpatched' => 'Вы пытаетесь откатить &laquo;непришитую&raquo; заплатку..',
      'revertingUnpatchedLegend' => 'Обычно это безопастно, но так как %sинформация о заплатке%s недоступна (она создаётся после её применения) откат может быть не точек или не возможен - проверьте лог после завершения операции.',

      'done' => 'Заплатка %s была успешно отменена.',
    ),

    'obliterate' => array(
      'pageTitle' => 'Удаление информации о заплатке',

      'done' => 'Заплатка %s была успешна &laquo;забыта&raquo;.',
      'legend' => 'Теперь она будет отображена как &laquo;доступная&raquo;, а не применённая, хотя её изменения не были устранены.',

      'revertingUnpatched' => 'Это действие удалит только информацию о заплатке.',
      'revertingUnpatchedLegend' => 'Заплатка станет &laquo;непришитой&raquo;, но файлы, которые она изменила, будут оставлены как есть.',
    ),

    'diff' => array(
      'applyTitle' => 'Файлы после применения заплатки',
      'revertTitle' => 'Файлы после отката заплатки',
    ),

    'upload' => array(
      'pageTitle' => 'Загрузка новой заплатки',

      'noUpload' => 'Файл заплатки не был получен.',
      'unsafeName' => 'Подозрительное имя файла %s &mdash; действие прервано.',
      'destExists' => 'Файл %s уже существует.',
      'cannotWrite' => 'Не возможно записать заплатку в %s.',
      'invalidFile' => 'Загруженный файл не похож на правильную заплатку или он подпадает под настройку <kbd>ignorePatchFN</kbd>. Файл был сохранён в %s.',
      'doApply' => 'Применить эти изменения',
    ),

    'source' => array(
      'pageTitle' => 'Исходник заплатки',
      'unreadable' => 'Не возможно прочитать файл заплатки.',
    ),

    'changes' => array(
      'pageTitle' => 'Изменённые файлы',

      'legend' => 'Эта страница показывает изменения программных файлов или применённые заплатки с их влиянием.',
      'noFilesChanged' => 'Никакие файлы ещё не были изменены.',
      'noPatchChanges' => 'Этот патч не изменил никаких файлов.',

      'formGroup' => 'Показывать:',
      'formGroupFiles' => 'файлы',
      'formGroupPatches' => 'патчи',
      'formFileSort' => 'Сортировать файлы по:',
      'formFileSortTime' => 'времени изменения',
      'formFileSortNatural' => 'числовому имени',
      'formFileSortName' => 'обычному имени',
      'formSortDesc' => 'В обратном порядке',

      'patchCaption' => '%s, %s %s (%s) &mdash; %sпоказать%s',
      'diff' => ' &mdash; %sпоказать%s',
      'stateOnlyPatchesMsg' => '%sНекоторые заплатки%s были удалены из папки заплаток без отката их изменений.',
    ),

    'logs' => array(
      'pageTitle' => 'Журнальные файлы',

      'noLogs' => 'Нет файлов для показа. Папка журнала: %s.',
      'noLogsCurrent' => 'Новые сообщения будут записаны в %s.',
      'firstMsg' => ' (%s) &mdash; первое сообщение %s &mdash; %sудалить этот файл и старше%s.',
      'lastMsg' => ' (%s) &mdash; последнее сообщение %s &mdash; %sудалить этот файл и старше%s.',

      'formSort' => 'Сортировать по:',
      'formSortTime' => 'времени изменения',
      'formSortNatural' => 'числовому имени',
      'formSortName' => 'имени',
      'formSortAsc' => 'Старые сверху',

      'viewTitle' => 'Просмотр журнала',
      'nonExistingLog' => 'Файл журнала %s не существует.',
      'emptyLog' => 'Этот журнал не содержит записей.',
      'msgOrder' => 'Старые сообщения сверху &mdash; %sпрокрутить в конец%s.',

      'pruneTitle' => 'Удаление журнальных файлов',
      'pruneError' => ' &mdash; <strong>ошибка удаления</strong>',
    ),
  )
);