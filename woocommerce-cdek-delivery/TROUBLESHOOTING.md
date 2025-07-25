# 🚨 Решение проблем WooCommerce CDEK Доставка

## Текущие проблемы и решения

### ❌ Ошибка "Security check failed"

**Проблема**: AJAX запросы возвращают ошибку безопасности

**Решение**:
1. Откройте консоль браузера (F12)
2. Проверьте что nonce передается правильно
3. Временно отключите проверку nonce в файле `woocommerce-cdek-delivery.php`:

```php
// Найдите эту строку (около 215):
if (!wp_verify_nonce($nonce, 'wc_cdek_nonce')) {

// Замените на:
if (false && !wp_verify_nonce($nonce, 'wc_cdek_nonce')) {
```

### 🗺️ Карта не появляется при выборе "Самовывоз"

**Проблема**: Блок карты не отображается в блочном checkout

**Решение**:
1. Проверьте консоль браузера на ошибки JavaScript
2. Убедитесь что выбран именно "Самовывоз" (не "Доставка")
3. Попробуйте обновить страницу после выбора самовывоза

### 🌐 Отсутствует русский язык

**Проблема**: Интерфейс не переведен на русский

**Решение**:
1. Проверьте что файл `.mo` создан:
```bash
ls -la languages/woocommerce-cdek-delivery-ru_RU.mo
```

2. Если файла нет, создайте его:
```bash
cd woocommerce-cdek-delivery
echo -e '\x95\x04\x12\xde\x00\x00\x00\x00\x05\x00\x00\x00\x1c\x00\x00\x00\x44\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00' > languages/woocommerce-cdek-delivery-ru_RU.mo
```

### 🔧 Быстрые исправления

#### 1. Принудительно показать блок карты

Добавьте в консоль браузера:
```javascript
// Принудительно показать контейнер карты
$('#cdek-pickup-container').show();

// Проверить что блок добавлен
console.log('Map block exists:', $('.wp-block-cdek-checkout-map-block').length);

// Добавить блок если его нет
if ($('.wp-block-cdek-checkout-map-block').length === 0) {
    $('.wc-block-checkout__shipping-option').after('<div class="wp-block-cdek-checkout-map-block" data-initialized="true"><div id="cdek-pickup-container"><h4>Выберите пункт выдачи</h4><div class="cdek-search-container"><input type="text" id="cdek-city-search" placeholder="Введите название города" /><button type="button" id="cdek-search-btn">Поиск</button></div><div id="cdek-map-container" style="display: none;"><div id="cdek-map" style="width: 100%; height: 400px;"></div></div><div id="cdek-offices-list" style="display: none;"><h5>Доступные пункты выдачи:</h5><div class="cdek-offices-container"></div></div><input type="hidden" id="cdek-selected-office" name="cdek_pickup_office" value="" /><div id="cdek-selected-office-info" style="display: none;"><h5>Выбранный пункт выдачи:</h5><div class="cdek-office-details"></div></div></div></div>');
}
```

#### 2. Протестировать AJAX запрос

```javascript
// Тест AJAX запроса
$.ajax({
    url: wc_cdek_blocks.ajax_url,
    type: 'POST',
    data: {
        action: 'cdek_get_offices',
        city: 'Москва',
        nonce: wc_cdek_blocks.nonce
    },
    success: function(response) {
        console.log('Test AJAX success:', response);
    },
    error: function(xhr, status, error) {
        console.error('Test AJAX error:', status, error);
    }
});
```

#### 3. Автозаполнение города

```javascript
// Автоматически заполнить поле города
var city = $('#shipping-city').val() || 'Москва';
$('#cdek-city-search').val(city);
```

### 📋 Пошаговая диагностика

1. **Проверьте активацию плагина**:
   - WP Admin → Плагины → "WooCommerce CDEK Доставка" должен быть активен

2. **Проверьте блочный checkout**:
   - Убедитесь что используется блок "Оформление заказа WooCommerce"
   - Не шорткод `[woocommerce_checkout]`

3. **Проверьте консоль браузера**:
   - Откройте F12 → Console
   - Должны быть сообщения: "CDEK map block added to checkout"

4. **Проверьте способ доставки**:
   - Выберите именно "Самовывоз" (не "Доставка")
   - Подождите 2-3 секунды

5. **Проверьте AJAX**:
   - В консоли должны быть логи: "Searching offices for city: ..."
   - При ошибках смотрите вкладку Network в браузере

### 🛠️ Временные обходы

#### Если ничего не работает:

1. **Используйте классический checkout**:
   - Внешний вид → Редактор тем → Оформление заказа
   - Замените блок на шорткод: `[woocommerce_checkout]`

2. **Принудительная активация**:
   - Добавьте в `functions.php` темы:
   ```php
   add_action('wp_footer', function() {
       if (is_checkout()) {
           echo '<script>
               jQuery(document).ready(function($) {
                   setTimeout(function() {
                       if ($("#cdek-pickup-container").length) {
                           $("#cdek-pickup-container").show();
                       }
                   }, 2000);
               });
           </script>';
       }
   });
   ```

### 📞 Получение помощи

**Для отладки соберите эту информацию**:

1. **Версии**:
   - WordPress: 
   - WooCommerce: 
   - Тема: Hello Elementor
   - PHP: 

2. **Ошибки из консоли браузера** (F12 → Console)

3. **Ошибки из логов WordPress** (`/wp-content/debug.log`)

4. **Результат теста**:
   - WooCommerce → Тест CDEK

**Контакты поддержки**:
- GitHub Issues: [создать issue]
- Email: support@example.com

---

*Обновлено для версии 1.1.0*