import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';
// Генератор красивого HTML отчета из официальной библиотеки jslib
import { htmlReport } from 'https://raw.githubusercontent.com/benc-uk/k6-reporter/main/dist/bundle.js';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.1/index.js';

// ==========================================
// 1. Кастомные бизнес-метрики
// ==========================================
const apiDuration = new Trend('api_req_duration', true);
const pageDuration = new Trend('page_req_duration', true);
const successfulOperations = new Counter('successful_operations');
const businessErrors = new Rate('business_errors');

// ==========================================
// 2. Конфигурация окружения
// ==========================================
const BASE_URL = __ENV.BASE_URL || 'https://tso.local';

// ==========================================
// 3. Настройки нагрузки и SLA/SLO (Thresholds)
// ==========================================
export const options = {
    // Игнорировать самоподписанные сертификаты для локальной разработки
    insecureSkipTLSVerify: true,

    // Сценарий ступенчатой нагрузки (Load / Stress profile)
    stages: [
        { duration: '30s', target: 10 }, // 1. Warm-up (разогрев до 10 пользователей)
        { duration: '1m', target: 30 },  // 2. Равномерный рост до 30 VU
        { duration: '2m', target: 30 },  // 3. Удержание рабочей нагрузки
        { duration: '30s', target: 50 }, // 4. Пиковый стресс-тест (50 VU)
        { duration: '30s', target: 0 },  // 5. Плавный спад (Cool-down)
    ],

    // Строгие критерии качества сервиса (SLO)
    thresholds: {
        // 95% запросов быстрее 500мс, 99% быстрее 800мс
        'http_req_duration': ['p(95)<500', 'p(99)<800'],
        // Для API: 95% быстрее 500мс
        'api_req_duration': ['p(95)<500'],
        // Ошибок меньше 1%
        'http_req_failed': ['rate<0.01'],
        'business_errors': ['rate<0.01'],
    },
};

// Заголовки по умолчанию (эмулируем уникальный IP для каждого виртуального пользователя)
function getHeaders() {
    return {
        'Accept': 'application/json',
        'User-Agent': 'k6-load-test/1.0',
        'X-Forwarded-For': `192.168.1.${(__VU % 250) + 1}`,
        'X-Real-IP': `192.168.1.${(__VU % 250) + 1}`,
    };
}

// ==========================================
// 4. Основной сценарий пользователя (User Journey)
// ==========================================
export default function () {
    const headers = getHeaders();

    // Вспомогательная функция для случайной паузы человека (1 - 3 сек)
    const thinkTime = (min = 1, max = 2.5) => {
        sleep(Math.random() * (max - min) + min);
    };

    // --- Шаг 1: Заход на главную страницу портала ---
    group('01_Open_Portal_Home', () => {
        const res = http.get(`${BASE_URL}/`, {
            headers: {
                'Accept': 'text/html',
                'X-Forwarded-For': headers['X-Forwarded-For'],
            },
            tags: { name: 'GET /' },
        });

        pageDuration.add(res.timings.duration);

        const ok = check(res, {
            'Homepage status is 200': (r) => r.status === 200,
            'Homepage has content': (r) => r.body && r.body.length > 0,
        });

        businessErrors.add(!ok);
    });

    thinkTime(1, 2);

    // --- Шаг 2: Получение метаданных и серверов рынка ---
    group('02_Get_Market_Servers', () => {
        const res = http.get(`${BASE_URL}/api/public/market/servers`, {
            headers: headers,
            tags: { name: 'GET /api/public/market/servers' },
        });

        apiDuration.add(res.timings.duration);

        const ok = check(res, {
            'Servers status is 200': (r) => r.status === 200,
            'Servers list is valid JSON': (r) => {
                try {
                    const data = r.json();
                    return Array.isArray(data) || typeof data === 'object';
                } catch (e) {
                    return false;
                }
            },
        });

        businessErrors.add(!ok);
        if (ok) successfulOperations.add(1);
    });

    thinkTime(1, 2);

    // --- Шаг 3: Загрузка каталога товаров (Goods Catalog) ---
    group('03_Get_Market_Goods', () => {
        const res = http.get(`${BASE_URL}/api/public/market/goods`, {
            headers: headers,
            tags: { name: 'GET /api/public/market/goods' },
        });

        apiDuration.add(res.timings.duration);

        const ok = check(res, {
            'Goods catalog status is 200': (r) => r.status === 200,
            'Goods response has ETag or data': (r) => r.headers['Etag'] !== undefined || r.body.length > 0,
        });

        businessErrors.add(!ok);
        if (ok) successfulOperations.add(1);
    });

    thinkTime(1.5, 3);

    // --- Шаг 4: Аналитика и популярные товары ---
    group('04_Get_Market_Analytics_And_Popular', () => {
        // Параллельный запрос двух связанных эндпоинтов
        const responses = http.batch([
            ['GET', `${BASE_URL}/api/public/market/popular`, null, { headers: headers, tags: { name: 'GET /api/public/market/popular' } }],
            ['GET', `${BASE_URL}/api/public/market/analytics`, null, { headers: headers, tags: { name: 'GET /api/public/market/analytics' } }],
        ]);

        const [popularRes, analyticsRes] = responses;

        apiDuration.add(popularRes.timings.duration);
        apiDuration.add(analyticsRes.timings.duration);

        const ok = check(popularRes, { 'Popular status is 200': (r) => r.status === 200 }) &&
                   check(analyticsRes, { 'Analytics status is 200': (r) => r.status === 200 });

        businessErrors.add(!ok);
        if (ok) successfulOperations.add(2);
    });

    thinkTime(2, 4);
}

// ==========================================
// 5. Генерация отчетов (Консоль + HTML файл)
// ==========================================
export function handleSummary(data) {
    return {
        'load-tests/summary.html': htmlReport(data), // Интерактивный дашборд
        stdout: textSummary(data, { indent: ' ', enableColors: true }), // Текстовый вывод в терминал
    };
}
