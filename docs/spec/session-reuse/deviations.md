# Журнал расхождений

> Часть спецификации **SPEC-001-session-reuse**. Навигация: [README](README.md) · [spec](spec.md) · [plan](plan.md) · [tasks](tasks.md) · [constitution](constitution.md)

## 13. Журнал расхождений (заполняет исполнитель)

| 2026-09-05 | Ф3.3 | В `TsoAuthService::verifySession` PHPStan ругался на тип `$cookieFile` для `CURLOPT_COOKIEJAR` (требуется `non-empty-string`). | Добавлена проверка `$cookieFile === '' || ! is_file($cookieFile)`, гарантирующая `non-empty-string` для анализатора. |
| 2026-09-05 | Ф4.2 | В коммите `6ff552e9` ранее был удалён вызов `/authenticate` из `HttpTsoClient::resolveServerUrl`, из-за чего якорь `Log::info("[TsoAmf] Load server authentication: ...")` отсутствовал. | Вызов `/authenticate` и логирование восстановлены в `resolveServerUrl()`, после чего добавлена проверка `isSessionRejected()` с выбросом `SessionExpiredException`. |
| 2026-09-05 | Ф6.3 | В коммите `6ff552e9` была закомментирована строка `$this->resetSession($account);` в `TsoAuthService::performLogin()`. | Строка раскомментирована согласно инвариантам INV-3 и INV-5, а также п. 11.3 research.md, обеспечивая чистую cookie-банку при полном логине. |
| 2026-09-05 | Ф8.2 | В тесте `SessionReuseTest::test_ensure_authenticated_skips_probe_when_flag_is_cached` PHPStan level 8 ругался на `assertTrue(true)`. | Заменено на строгую проверку кэша: `$this->assertTrue((bool) Cache::get("tso:session_ok:{$account->id}"));`. |

---

