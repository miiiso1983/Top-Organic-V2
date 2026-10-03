/**
 * منشئ التقويم واختيار الوقت للحجوزات
 * يستخدم Flatpickr مع أزرار اختصار للأوقات الشائعة
 */
(function () {
  'use strict';

  function initReservationPickers() {
    const timePresets = [
      { label: '12:00 ظهراً', time: '12:00' },
      { label: '1:00 بعد الظهر', time: '13:00' },
      { label: '6:00 مساءً', time: '18:00' },
      { label: '7:00 مساءً', time: '19:00' },
      { label: '8:00 مساءً', time: '20:00' },
      { label: '9:00 مساءً', time: '21:00' },
    ];

    const fpConfig = {
      locale: 'ar',
      enableTime: true,
      dateFormat: 'Y-m-d H:i',
      minDate: 'today',
      time_24hr: false,
      minuteIncrement: 15,
      allowInput: true,
      disableMobile: false,
      position: 'below',
      theme: 'material_green',
      onReady: function (selectedDates, dateStr, instance) {
        const calendar = instance.calendarContainer;
        if (!calendar || calendar.querySelector('.fp-time-presets')) return;

        const presetsWrap = document.createElement('div');
        presetsWrap.className = 'fp-time-presets';
        presetsWrap.setAttribute('dir', 'rtl');

        const title = document.createElement('div');
        title.className = 'fp-presets-title';
        title.textContent = 'اختيار سريع للوقت';
        presetsWrap.appendChild(title);

        const buttonsWrap = document.createElement('div');
        buttonsWrap.className = 'fp-presets-buttons';

        timePresets.forEach(function (preset) {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'fp-preset-btn';
          btn.textContent = preset.label;
          btn.addEventListener('click', function () {
            const current = instance.selectedDates[0] || new Date();
            const [h, m] = preset.time.split(':');
            current.setHours(parseInt(h, 10), parseInt(m, 10), 0, 0);
            instance.setDate(current, true);
          });
          buttonsWrap.appendChild(btn);
        });

        presetsWrap.appendChild(buttonsWrap);
        calendar.appendChild(presetsWrap);
      },
    };

    document.querySelectorAll('.reservation-datetime').forEach(function (input) {
      if (input.dataset.fpInitialized) return;
      input.dataset.fpInitialized = '1';
      flatpickr(input, fpConfig);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReservationPickers);
  } else {
    initReservationPickers();
  }
})();
