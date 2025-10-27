jQuery(document).ready(function($) {
    'use strict';
    
    var cdekMap = null;
    var cdekPlacemarks = [];
    var selectedOffice = null;
    
    // Инициализация карты
    function initMap() {
        if (typeof ymaps === 'undefined') {
            console.error('Yandex Maps API not loaded');
            return;
        }
        
        ymaps.ready(function() {
            cdekMap = new ymaps.Map('cdek-map', {
                center: [55.76, 37.64], // Москва по умолчанию
                zoom: 10,
                controls: ['zoomControl', 'fullscreenControl']
            });
        });
    }
    
    // Поиск города и пунктов выдачи
    function searchOffices(city) {
        if (!city) {
            showError('Введите название города');
            return;
        }
        
        showLoading();
        
        $.ajax({
            url: wc_cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cdek_get_offices',
                city: city,
                nonce: wc_cdek_ajax.nonce
            },
            success: function(response) {
                hideLoading();
                
                if (response.success && response.data.length > 0) {
                    displayOffices(response.data);
                    displayOfficesOnMap(response.data);
                    $('#cdek-map-container').show();
                    $('#cdek-offices-list').show();
                } else {
                    showError(wc_cdek_ajax.i18n.no_offices);
                }
            },
            error: function() {
                hideLoading();
                showError(wc_cdek_ajax.i18n.error);
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
        
        // Обновляем checkout
        $('body').trigger('update_checkout');
    }
    
    // Отображение выбранного пункта
    function displaySelectedOffice(office) {
        var html = '<div class="cdek-office-name">' + (office.name || 'CDEK ' + office.code) + '</div>';
        html += '<div class="cdek-office-address">' + office.address + '</div>';
        
        if (office.phone) {
            html += '<div class="cdek-office-phone">Телефон: ' + office.phone + '</div>';
        }
        
        if (office.work_time) {
            html += '<div class="cdek-office-hours">' + office.work_time + '</div>';
        }
        
        $('.cdek-office-details').html(html);
        $('#cdek-selected-office-info').show();
    }
    
    // Показать загрузку
    function showLoading() {
        $('.cdek-offices-container').html('<div class="cdek-loading">' + wc_cdek_ajax.i18n.loading + '</div>');
    }
    
    // Скрыть загрузку
    function hideLoading() {
        // Загрузка будет скрыта при отображении результатов
    }
    
    // Показать ошибку
    function showError(message) {
        $('.cdek-offices-container').html('<div class="cdek-error">' + message + '</div>');
    }
    
    // Обработчики событий
    $('#cdek-search-btn').on('click', function() {
        var city = $('#cdek-city-search').val().trim();
        if (city) {
            searchOffices(city);
        }
    });
    
    $('#cdek-city-search').on('keypress', function(e) {
        if (e.which === 13) { // Enter
            e.preventDefault();
            var city = $(this).val().trim();
            if (city) {
                searchOffices(city);
            }
        }
    });
    
    // Клик по пункту выдачи в списке
    $(document).on('click', '.cdek-office-item', function() {
        var office = JSON.parse($(this).attr('data-office'));
        selectOffice(office);
        
        // Центрируем карту на выбранном пункте
        if (cdekMap && office.latitude && office.longitude) {
            cdekMap.setCenter([parseFloat(office.latitude), parseFloat(office.longitude)], 15);
        }
    });
    
    // Обработчик изменения способа доставки
    $(document.body).on('updated_checkout', function() {
        var selectedShipping = $('input[name^="shipping_method"]:checked').val();
        
        if (selectedShipping && selectedShipping.indexOf('cdek_pickup') !== -1) {
            $('.cdek-pickup-selector').show();
            
            // Инициализируем карту если еще не инициализирована
            if (!cdekMap) {
                setTimeout(initMap, 100);
            }
            
            // Автоматически ищем пункты выдачи по городу из формы
            var city = $('#cdek-city-search').val().trim();
            if (city && !selectedOffice) {
                searchOffices(city);
            }
        } else {
            $('.cdek-pickup-selector').hide();
            selectedOffice = null;
            $('#cdek-selected-office').val('');
        }
    });
    
    // Расчет стоимости доставки
    function calculateDeliveryCost() {
        var fromCity = '393'; // Саратов
        var toCity = $('#shipping_city').val() || $('#billing_city').val();
        var postcode = $('#shipping_postcode').val() || $('#billing_postcode').val();
        
        if (!toCity && !postcode) {
            return;
        }
        
        // Собираем данные о товарах для расчета веса и размеров
        var totalWeight = 0;
        var maxLength = 0;
        var maxWidth = 0;
        var maxHeight = 0;
        
        // Эти данные обычно передаются с сервера или рассчитываются
        // В реальной реализации нужно получать их из корзины
        
        $.ajax({
            url: wc_cdek_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'cdek_calculate_delivery',
                from_city: fromCity,
                to_city: postcode || toCity,
                weight: totalWeight || 100,
                length: maxLength || 10,
                width: maxWidth || 10,
                height: maxHeight || 10,
                delivery_type: 'pickup',
                nonce: wc_cdek_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    console.log('Delivery cost calculated:', response);
                } else {
                    console.error('Delivery calculation error:', response.error);
                }
            }
        });
    }
    
    // Автозаполнение города при изменении адреса
    $('#shipping_city, #billing_city').on('change', function() {
        var city = $(this).val();
        if (city && $('#cdek-city-search').val() !== city) {
            $('#cdek-city-search').val(city);
        }
    });
    
    // Валидация при отправке формы
    $(document).on('checkout_place_order', function() {
        var selectedShipping = $('input[name^="shipping_method"]:checked').val();
        
        if (selectedShipping && selectedShipping.indexOf('cdek_pickup') !== -1) {
            if (!selectedOffice) {
                alert('Пожалуйста, выберите пункт выдачи CDEK');
                return false;
            }
        }
        
        return true;
    });
    
    // Инициализация при загрузке страницы
    if ($('.cdek-pickup-selector').length > 0) {
        // Проверяем, выбран ли CDEK самовывоз
        var selectedShipping = $('input[name^="shipping_method"]:checked').val();
        if (selectedShipping && selectedShipping.indexOf('cdek_pickup') !== -1) {
            setTimeout(initMap, 500);
        }
    }
    
    // Автокомплит для поиска городов
    var searchTimeout;
    $('#cdek-city-search').on('input', function() {
        var query = $(this).val().trim();
        
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }
        
        if (query.length >= 3) {
            searchTimeout = setTimeout(function() {
                // Можно добавить автокомплит городов через CDEK API
                // searchCities(query);
            }, 300);
        }
    });
});