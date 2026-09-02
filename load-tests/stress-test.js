import http from 'k6/http';
import { check } from 'k6';
import { Trend, Rate } from 'k6/metrics';
import { htmlReport } from 'https://raw.githubusercontent.com/benc-uk/k6-reporter/main/dist/bundle.js';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.1/index.js';

const apiDuration = new Trend('api_req_duration', true);
const errorRate = new Rate('errors');

const BASE_URL = __ENV.BASE_URL || 'https://tso.local';

// =========================================================================
// Настройка нагрузки через целевой RPS (Arrival Rate)
// k6 будет сам контролировать частоту генерации запросов в секунду
// =========================================================================
export const options = {
    insecureSkipTLSVerify: true,

    scenarios: {
        high_throughput_stress: {
            executor: 'ramping-arrival-rate', // Контролирует точный RPS
            startRate: 10,                   // Начинаем с 10 RPS
            timeUnit: '1s',
            preAllocatedVUs: 50,             // Заранее выделенный пул воркеров
            maxVUs: 200,                     // Максимальный пул под пиковый RPS
            stages: [
                { target: 25, duration: '30s' },  // Разгон до 25 RPS
                { target: 50, duration: '1m' },   // Рост до 50 RPS (серьезная нагрузка)
                { target: 100, duration: '1m' },  // Стресс: 100 RPS
                { target: 150, duration: '30s' }, // Пиковый стресс: 150 RPS
                { target: 0, duration: '30s' },   // Снижение нагрузки
            ],
        },
    },

    thresholds: {
        'http_req_duration': ['p(95)<600'],
        'errors': ['rate<0.05'], // Допускаем не более 5% ошибок на пределе
    },
};

export default function () {
    const vuId = __VU || 1;
    const headers = {
        'Accept': 'application/json',
        'User-Agent': 'k6-stress-test/1.0',
        'X-Forwarded-For': `10.0.${Math.floor(vuId / 250)}.${(vuId % 250) + 1}`,
    };

    // Случайный выбор одного из тяжелых эндпоинтов API без всяких задержек
    const endpoints = [
        '/api/public/market/goods',
        '/api/public/market/servers',
        '/api/public/market/popular',
        '/api/public/market/analytics',
    ];
    const url = `${BASE_URL}${endpoints[Math.floor(Math.random() * endpoints.length)]}`;

    const res = http.get(url, { headers, tags: { name: 'API_Request' } });

    apiDuration.add(res.timings.duration);

    const isOk = check(res, {
        'Status is 200': (r) => r.status === 200,
    });

    // Передаем true/false на каждый запрос, чтобы Rate корректно считал процент ошибок от общего числа
    errorRate.add(!isOk);
}

export function handleSummary(data) {
    return {
        'load-tests/stress-summary.html': htmlReport(data),
        stdout: textSummary(data, { indent: ' ', enableColors: true }),
    };
}
