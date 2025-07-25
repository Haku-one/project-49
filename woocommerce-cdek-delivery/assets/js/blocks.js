jQuery(document).ready(function($) {
    'use strict';
    
    var cdekMap = null;
    var cdekPlacemarks = [];
    var selectedOffice = null;
    var isBlockCheckout = $('.wp-block-woocommerce-checkout').length > 0;
    
    // Инициализация для блочного checkout
    if (isBlockCheckout) {
        initBlockCheckoutIntegration();
    }
    
    function initBlockCheckoutIntegration() {
        // Ждем загрузки блоков WooCommerce
        if (typeof wp !== 'undefined' && wp.hooks) {
            // Добавляем наш блок карты после блока способов доставки
            wp.hooks.addFilter(
                'woocommerce_blocks_checkout_shipping_methods_after',
                'cdek/add-map-block',
                addCdekMapBlock
            );
        }
        
        // Наблюдаем за изменениями в DOM для обнаружения блока карты
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes) {
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) {
                            var mapBlock = node.querySelector('.wp-block-cdek-checkout-map-block');
                            if (mapBlock) {
                                initCdekMapBlock(mapBlock);
                            }
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
        
        // Проверяем, есть ли уже блок карты на странице
        var existingMapBlock = document.querySelector('.wp-block-cdek-checkout-map-block');
        if (existingMapBlock) {
            initCdekMapBlock(existingMapBlock);
        }
        
        // Слушаем изменения способа доставки
        $(document).on('change', 'input[name*="shipping_method"]', function() {
            handleShippingMethodChange();
        });
        
        // Слушаем клики по способам доставки в блочном checkout
        $(document).on('click', '.wc-block-checkout__shipping-method-option', function() {
            setTimeout(handleShippingMethodChange, 500);
        });
        
        // Наблюдаем за изменениями в DOM
        var shippingObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'aria-checked') {
                    setTimeout(handleShippingMethodChange, 100);
                }
            });
        });
        
        // Запускаем наблюдение за способами доставки
        var shippingMethods = document.querySelectorAll('.wc-block-checkout__shipping-method-option');
        shippingMethods.forEach(function(method) {
            shippingObserver.observe(method, { attributes: true });
        });
        
        // Проверяем при загрузке страницы
        setTimeout(handleShippingMethodChange, 1000);
        setTimeout(handleShippingMethodChange, 3000); // Дополнительная проверка
    }
    
    function addCdekMapBlock(content) {
        return content + '<div class="wp-block-cdek-checkout-map-block"></div>';
    }
    
    function initCdekMapBlock(mapBlock) {
        if (mapBlock.dataset.initialized) {
            return;
        }
        
        mapBlock.dataset.initialized = 'true';
        
        var html = `
            <div id="cdek-pickup-container" style="display: none;">
                <h4>${wc_cdek_blocks.i18n.select_office}</h4>
                
                <div class="cdek-search-container">
                    <input type="text" id="cdek-city-search" placeholder="${wc_cdek_blocks.i18n.enter_city}" />
                    <button type="button" id="cdek-search-btn">${wc_cdek_blocks.i18n.search}</button>
                </div>
                
                <div id="cdek-map-container" style="display: none;">
                    <div id="cdek-map" style="width: 100%; height: 400px;"></div>
                </div>
                
                <div id="cdek-offices-list" style="display: none;">
                    <h5>${wc_cdek_blocks.i18n.available_points}</h5>
                    <div class="cdek-offices-container"></div>
                </div>
                
                <input type="hidden" id="cdek-selected-office" name="cdek_pickup_office" value="" />
                <div id="cdek-selected-office-info" style="display: none;">
                    <h5>${wc_cdek_blocks.i18n.selected_point}</h5>
                    <div class="cdek-office-details"></div>
                </div>
            </div>
        `;
        
        mapBlock.innerHTML = html;
        
        // Привязываем обработчики событий
        bindCdekEvents();
    }
    
    function bindCdekEvents() {
        // Поиск пунктов выдачи
        $(document).off('click', '#cdek-search-btn').on('click', '#cdek-search-btn', function() {
            var city = $('#cdek-city-search').val().trim();
            if (city) {
                searchOffices(city);
            }
        });
        
        $(document).off('keypress', '#cdek-city-search').on('keypress', '#cdek-city-search', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                var city = $(this).val().trim();
                if (city) {
                    searchOffices(city);
                }
            }
        });
        
        // Клик по пункту выдачи
        $(document).off('click', '.cdek-office-item').on('click', '.cdek-office-item', function() {
            var office = JSON.parse($(this).attr('data-office'));
            selectOffice(office);
            
            if (cdekMap && office.latitude && office.longitude) {
                cdekMap.setCenter([parseFloat(office.latitude), parseFloat(office.longitude)], 15);
            }
        });
        
        // Автозаполнение города из формы доставки
        setTimeout(function() {
            var city = getShippingCity();
            if (city) {
                $('#cdek-city-search').val(city);
            }
        }, 500);
    }
    
    // Глобальная функция для инициализации событий
    window.initCdekBlockEvents = function() {
        bindCdekEvents();
        setTimeout(handleShippingMethodChange, 500);
    };
    
    function handleShippingMethodChange() {
        var selectedMethod = $('input[name*="shipping_method"]:checked').val();
        var cdekContainer = $('#cdek-pickup-container');
        
        // Проверяем выбранный способ доставки по тексту
        var selectedShippingText = $('.wc-block-checkout__shipping-method-option--selected .wc-block-checkout__shipping-method-option-title').text();
        var isPickupSelected = selectedShippingText && selectedShippingText.toLowerCase().indexOf('самовывоз') !== -1;
        
        console.log('Selected shipping method:', selectedMethod);
        console.log('Selected shipping text:', selectedShippingText);
        console.log('Is pickup selected:', isPickupSelected);
        
        if (isPickupSelected || (selectedMethod && (selectedMethod.indexOf('cdek') !== -1 || selectedMethod.indexOf('pickup') !== -1))) {
            cdekContainer.show();
            
            if (!cdekMap) {
                setTimeout(initMap, 100);
            }
            
            // Автоматически заполняем город из формы
            var city = getShippingCity();
            if (city) {
                $('#cdek-city-search').val(city);
                if (!selectedOffice) {
                    searchOffices(city);
                }
            }
        } else {
            cdekContainer.hide();
            selectedOffice = null;
            $('#cdek-selected-office').val('');
        }
    }
    
    function getShippingCity() {
        // Ищем поле города в блочном checkout
        var cityField = $('input[id*="shipping-city"], input[id*="billing-city"]').first();
        if (cityField.length) {
            return cityField.val();
        }
        
        // Альтернативный поиск
        var cityInputs = $('input[type="text"]').filter(function() {
            var id = $(this).attr('id') || '';
            var name = $(this).attr('name') || '';
            return id.includes('city') || name.includes('city');
        });
        
        if (cityInputs.length) {
            return cityInputs.first().val();
        }
        
        return '';
    }
    
    // Инициализация карты
    function initMap() {
        if (typeof ymaps === 'undefined') {
            console.error('Yandex Maps API not loaded');
            return;
        }
        
        ymaps.ready(function() {
            if (!document.getElementById('cdek-map')) {
                return;
            }
            
            cdekMap = new ymaps.Map('cdek-map', {
                center: [55.76, 37.64], // Москва по умолчанию
                zoom: 10,
                controls: ['zoomControl', 'fullscreenControl']
            });
        });
    }
    
    // Поиск пунктов выдачи
    function searchOffices(city) {
        if (!city) {
            showError('Введите название города');
            return;
        }
        
        console.log('Searching offices for city:', city);
        console.log('AJAX URL:', wc_cdek_blocks.ajax_url);
        console.log('Nonce:', wc_cdek_blocks.nonce);
        
        showLoading();
        
        $.ajax({
            url: wc_cdek_blocks.ajax_url,
            type: 'POST',
            data: {
                action: 'cdek_get_offices',
                city: city,
                nonce: wc_cdek_blocks.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                hideLoading();
                
                if (response.success && response.data && response.data.length > 0) {
                    displayOffices(response.data);
                    displayOfficesOnMap(response.data);
                    $('#cdek-map-container').show();
                    $('#cdek-offices-list').show();
                } else if (response.success && response.length > 0) {
                    // Fallback для прямого ответа
                    displayOffices(response);
                    displayOfficesOnMap(response);
                    $('#cdek-map-container').show();
                    $('#cdek-offices-list').show();
                } else {
                    var errorMsg = response.data ? response.data.message : 'Пункты выдачи не найдены';
                    showError(errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error, xhr.responseText);
                hideLoading();
                showError('Ошибка загрузки данных: ' + error);
            }
        });
    }
    
    // Отображение списка пунктов выдачи
    function displayOffices(offices) {
        var container = $('.cdek-offices-container');
        container.empty();
        
        offices.forEach(function(office) {
            var officeHtml = '<div class="cdek-office-item" data-office=\'' + JSON.stringify(office) + '\'>';
            officeHtml += '<div class="cdek-office-name">' + (office.name || 'CDEK ' + office.code) + '</div>';
            officeHtml += '<div class="cdek-office-address">' + office.address + '</div>';
            
            if (office.phone) {
                officeHtml += '<div class="cdek-office-phone">' + wc_cdek_blocks.i18n.phone + ' ' + office.phone + '</div>';
            }
            
            if (office.work_time) {
                officeHtml += '<div class="cdek-office-hours">' + office.work_time + '</div>';
            }
            
            officeHtml += '</div>';
            
            container.append(officeHtml);
        });
    }
    
    // Отображение пунктов на карте
    function displayOfficesOnMap(offices) {
        if (!cdekMap || !ymaps) {
            return;
        }
        
        // Очищаем предыдущие метки
        cdekPlacemarks.forEach(function(placemark) {
            cdekMap.geoObjects.remove(placemark);
        });
        cdekPlacemarks = [];
        
        var bounds = [];
        
        offices.forEach(function(office) {
            if (office.latitude && office.longitude) {
                var coords = [parseFloat(office.latitude), parseFloat(office.longitude)];
                bounds.push(coords);
                
                var placemark = new ymaps.Placemark(coords, {
                    balloonContentHeader: office.name || 'CDEK ' + office.code,
                    balloonContentBody: office.address + (office.phone ? '<br>' + wc_cdek_blocks.i18n.phone + ' ' + office.phone : ''),
                    balloonContentFooter: office.work_time || '',
                    hintContent: office.address
                }, {
                    preset: 'islands#redDotIcon'
                });
                
                placemark.events.add('click', function() {
                    selectOffice(office);
                });
                
                cdekMap.geoObjects.add(placemark);
                cdekPlacemarks.push(placemark);
            }
        });
        
        // Устанавливаем границы карты
        if (bounds.length > 0) {
            cdekMap.setBounds(bounds, {
                checkZoomRange: true,
                zoomMargin: 20
            });
        }
    }
    
    // Выбор пункта выдачи
    function selectOffice(office) {
        selectedOffice = office;
        
        // Обновляем UI
        $('.cdek-office-item').removeClass('selected');
        $('.cdek-office-item').each(function() {
            var itemOffice = JSON.parse($(this).attr('data-office'));
            if (itemOffice.code === office.code) {
                $(this).addClass('selected');
            }
        });
        
        // Показываем информацию о выбранном пункте
        displaySelectedOffice(office);
        
        // Сохраняем в скрытое поле
        $('#cdek-selected-office').val(JSON.stringify(office));
        
        // Для блочного checkout отправляем данные через Store API
        if (isBlockCheckout && typeof wp !== 'undefined' && wp.data) {
            wp.data.dispatch('wc/store/checkout').setAdditionalInformation({
                cdek_pickup_office: office
            });
        }
        
        // Обновляем checkout
        $('body').trigger('update_checkout');
    }
    
    // Отображение выбранного пункта
    function displaySelectedOffice(office) {
        var html = '<div class="cdek-office-name">' + (office.name || 'CDEK ' + office.code) + '</div>';
        html += '<div class="cdek-office-address">' + wc_cdek_blocks.i18n.address + ' ' + office.address + '</div>';
        
        if (office.phone) {
            html += '<div class="cdek-office-phone">' + wc_cdek_blocks.i18n.phone + ' ' + office.phone + '</div>';
        }
        
        if (office.work_time) {
            html += '<div class="cdek-office-hours">' + wc_cdek_blocks.i18n.work_time + ' ' + office.work_time + '</div>';
        }
        
        $('.cdek-office-details').html(html);
        $('#cdek-selected-office-info').show();
    }
    
    // Показать загрузку
    function showLoading() {
        $('.cdek-offices-container').html('<div class="cdek-loading">' + wc_cdek_blocks.i18n.loading + '</div>');
    }
    
    // Скрыть загрузку
    function hideLoading() {
        // Загрузка будет скрыта при отображении результатов
    }
    
    // Показать ошибку
    function showError(message) {
        $('.cdek-offices-container').html('<div class="cdek-error">' + message + '</div>');
    }
    
    // Валидация при отправке заказа
    $(document).on('checkout_place_order', function() {
        var selectedMethod = $('input[name*="shipping_method"]:checked').val();
        
        if (selectedMethod && (selectedMethod.indexOf('cdek') !== -1 || selectedMethod.indexOf('pickup') !== -1)) {
            if (!selectedOffice) {
                alert(wc_cdek_blocks.i18n.select_point_required);
                return false;
            }
        }
        
        return true;
    });
    
    // Для блочного checkout - валидация через хуки
    if (isBlockCheckout && typeof wp !== 'undefined' && wp.hooks) {
        wp.hooks.addFilter(
            'woocommerce_blocks_checkout_validation',
            'cdek/validate-pickup-point',
            function(result, data) {
                var shippingMethods = data.shipping_address?.shipping_method || [];
                var needsCdekValidation = shippingMethods.some(function(method) {
                    return method.includes('cdek') || method.includes('pickup');
                });
                
                if (needsCdekValidation && !selectedOffice) {
                    result.errorMessage = wc_cdek_blocks.i18n.select_point_required;
                    return false;
                }
                
                return result;
            }
        );
    }
});