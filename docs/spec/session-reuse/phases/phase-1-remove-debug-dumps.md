# Фаза Ф1. Удаление отладочных дампов

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](../README.md) · [spec](../spec.md) · [plan](../plan.md) · [tasks](../tasks.md) · [constitution](../constitution.md)

### Шаги

- [ ] **Ф1.1.** Удалить строку (якорь дословный):

```php
        Storage::disk('local')->put('debug/play_page.html', $html);
```

  Вместе с ней удалить пустую строку, оставшуюся от отступа, чтобы не было двух пустых строк подряд.

- [ ] **Ф1.2.** Удалить конструкцию (якорь дословный, обратите внимание на перенос строки внутри вызова):

```php
        Storage::disk('local')->put('debug/flash_vars.json', (string) json_encode($params, JSON_THROW_ON_ERROR
                                                                                          | JSON_PRETTY_PRINT));
```

- [ ] **Ф1.3.** Метод после правки должен выглядеть ровно так (кроме docblock, который не меняется):

```php
    private function extractParams(string $html): array
    {
        $params = [];
        if (preg_match('/return\s+"([^"]+)"/i', $html, $matches) || preg_match('/thisProgram:\s+"([^"]+)"/i', $html, $matches)) {
            parse_str($matches[1], $parsedParams);
            $params = $parsedParams;
        }

        $nickName = 'Unknown';
        if (preg_match("/loggedInUserName\s*=\s*'([^']+)'/i", $html, $matches)) {
            $nickName = $matches[1];
        }

        if (empty($params) || ! isset($params['dsoAuthToken'])) {
            throw new RuntimeException('Could not extract auth tokens from play page. Possible captcha or maintenance.');
        }

        return [
            'dsoAuthToken' => $params['dsoAuthToken'],
            'dsoAuthUser' => $params['dsoAuthUser'],
            'bburl' => $params['bb'],
            'zoneId' => $params['zoneID'] ?? null,
            'nickName' => $nickName,
        ];
    }
```

  Подводный камень: **регулярные выражения и текст исключения не менять** — они завязаны на разметку страницы `play` и на тесты.

- [ ] **Ф1.4.** Убрать ставший ненужным импорт. Сначала проверить, что `Storage` больше не используется в файле:

```bash
grep -n 'Storage' app/Services/TsoAuthService.php
```

  Если совпадений, кроме строки `use Illuminate\Support\Facades\Storage;`, нет — удалить эту строку. Если есть другие использования — импорт оставить (и отметить это в [../research.md](../research.md)).

- [ ] **Ф1.5.** Удалить артефакты, оставшиеся на диске, и убедиться, что каталог не в git:

```bash
rm -f storage/app/debug/play_page.html storage/app/debug/flash_vars.json
rmdir storage/app/debug 2>/dev/null || true
git ls-files storage/app/debug
```

  Последняя команда должна вывести пустоту. Если файлы под контролем версий — удалить их через `git rm --cached`.

- [ ] **Ф1.6.** Гейты [../quickstart.md](../quickstart.md) зелёные. Коммит: `fix(auth): stop dumping play page and flash vars to storage`.

---

