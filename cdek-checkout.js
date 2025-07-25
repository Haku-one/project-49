jQuery(document).ready(function($) {
    'use strict';
    
    var cdekMap = null;
    var cdekPlacemarks = [];
    var selectedOffice = null;
    
    // Глобальная функция инициализации
    window.initCdekCheckout = function() {
        console.log('Initializing CDEK checkout functionality');
        
        // Привязываем события
        bindCdekEvents();
        
        // Проверяем способ доставки
        handleShippingMethodChange();
        
        // Наблюдаем за изменениями способа доставки
        observeShippingChanges();
    };
    
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
        
        // Автозаполнение города
        setTimeout(function() {
            var city = getShippingCity();
            if (city) {
                $('#cdek-city-search').val(city);
            }
        }, 1000);
    }
    
    function observeShippingChanges() {
        // Слушаем клики по способам доставки
        $(document).on('click', '.wc-block-checkout__shipping-method-option', function() {
            setTimeout(handleShippingMethodChange, 500);
        });
        
        // Наблюдаем за изменениями атрибутов
        var shippingObserver = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'aria-checked') {
                    setTimeout(handleShippingMethodChange, 100);
                }
            });
        });
        
        // Запускаем наблюдение
        var shippingMethods = document.querySelectorAll('.wc-block-checkout__shipping-method-option');
        shippingMethods.forEach(function(method) {
            shippingObserver.observe(method, { attributes: true });
        });
        
        // Периодические проверки
        setInterval(handleShippingMethodChange, 2000);
    }
    
    function handleShippingMethodChange() {
        var cdekContainer = $('#cdek-pickup-container');
        
        // Проверяем выбранный способ доставки по тексту
        var selectedShippingText = $('.wc-block-checkout__shipping-method-option--selected .wc-block-checkout__shipping-method-option-title').text();
        var isPickupSelected = selectedShippingText && selectedShippingText.toLowerCase().indexOf('самовывоз') !== -1;
        
        console.log('Selected shipping text:', selectedShippingText);
        console.log('Is pickup selected:', isPickupSelected);
        
        if (isPickupSelected) {
            cdekContainer.show();
            
            if (!cdekMap) {
                setTimeout(initMap, 500);
            }
            
            // Автоматически заполняем город
            var city = getShippingCity();
            if (city && city !== $('#cdek-city-search').val()) {
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
        var cityField = $('#shipping-city');
        if (cityField.length && cityField.val()) {
            return cityField.val();
        }
        
        // Альтернативный поиск
        var cityInputs = $('input[type="text"]').filter(function() {
            var id = $(this).attr('id') || '';
            return id.includes('city');
        });
        
        if (cityInputs.length) {
            var value = cityInputs.first().val();
            if (value) {
                return value;
            }
        }
        
        return 'Москва'; // По умолчанию
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
            
            console.log('Yandex Map initialized');
        });
    }
    
    // Поиск пунктов выдачи
    function searchOffices(city) {
        if (!city) {
            showError('Введите название города');
            return;
        }
        
        console.log('Searching offices for city:', city);
        
        showLoading();
        
        $.ajax({
            url: cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cdek_get_offices',
                city: city,
                nonce: cdek_ajax.nonce
            },
            success: function(response) {
                console.log('AJAX response:', response);
                hideLoading();
                
                if (response.success && response.data && response.data.length > 0) {
                    displayOffices(response.data);
                    displayOfficesOnMap(response.data);
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
                officeHtml += '<div class="cdek-office-phone">Телефон: ' + office.phone + '</div>';
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
                    balloonContentBody: office.address + (office.phone ? '<br>Телефон: ' + office.phone : ''),
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
        
        console.log('Office selected:', office);
    }
    
    // Отображение выбранного пункта
    function displaySelectedOffice(office) {
        var html = '<div class="cdek-office-name">' + (office.name || 'CDEK ' + office.code) + '</div>';
        html += '<div class="cdek-office-address">Адрес: ' + office.address + '</div>';
        
        if (office.phone) {
            html += '<div class="cdek-office-phone">Телефон: ' + office.phone + '</div>';
        }
        
        if (office.work_time) {
            html += '<div class="cdek-office-hours">Время работы: ' + office.work_time + '</div>';
        }
        
        $('.cdek-office-details').html(html);
        $('#cdek-selected-office-info').show();
    }
    
    // Показать загрузку
    function showLoading() {
        $('.cdek-offices-container').html('<div class="cdek-loading">Загрузка...</div>');
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
    $(document).on('click', '.wc-block-components-checkout-place-order-button', function(e) {
        var selectedShippingText = $('.wc-block-checkout__shipping-method-option--selected .wc-block-checkout__shipping-method-option-title').text();
        var isPickupSelected = selectedShippingText && selectedShippingText.toLowerCase().indexOf('самовывоз') !== -1;
        
        if (isPickupSelected && !selectedOffice) {
            e.preventDefault();
            alert('Пожалуйста, выберите пункт выдачи CDEK');
            return false;
        }
    });
    
    // Инициализация при загрузке
    setTimeout(function() {
        if (typeof window.initCdekCheckout === 'function') {
            window.initCdekCheckout();
        }
    }, 2000);
});