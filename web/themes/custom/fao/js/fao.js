"use strict";


(function($, Drupal) {
  // Valeria: neede dto inject the vocablary machine name into a dependent fiter controlling filter in the taxonomuy term view, to allow for some onditional reasoning: 

  Drupal.behaviors.injectVocabulary = {
    attach: function(context, settings) {
      once('inject-vocabulary', '#vocabulary-data', context).forEach(function(el) {
        var vocabulary = $(el).data('vocabulary');
        if (vocabulary === 'unccd_land_degradation_guidance') {
          var $filter = $('[name="field_vocabulary_machine_names"]');
          if ($filter.length && !$filter.val()) {
            $filter.val(vocabulary);
            $filter.trigger('change');
            setTimeout(function() {
              $filter.closest('form').trigger('submit');
            }, 300);
          }
        }
      });
    }
  };
})(jQuery, Drupal);


(function ($, viewport) {

  Drupal.behaviors.fao = {

    attach: function (context, settings) {

      window.fao = {}

      fao.initSwiper = function () {
        const swiper = new Swiper('.swiper-banner', {
          // Navigation arrows
          navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
          },

        });
        const swiperCards = new Swiper('.swiper-partners', {
          // Navigation arrows
          autoplay: {
            delay: 5000,
          },
          pagination: {
            el: '.swiper-pagination',
            type: 'bullets',
            clickable: true
          },
          slidesPerView: '5',
          slidesPerGroup: '5',
          spaceBetween: 20,
          navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
          },

        });

const swiperElements = context.querySelectorAll('.carousel-swiper');

      swiperElements.forEach(el => {
        const slidesCount = el.querySelectorAll('.swiper-slide').length;
        const mobileCarousel = el.getAttribute('data-mobile-carousel');

        // If carousel_active is false (data-mobile-carousel="disabled"), skip on mobile
        if (mobileCarousel === 'disabled' && window.innerWidth < 768) {
          return; // Don't initialize Swiper on mobile
        }

        // Initialize Swiper with a slight delay to ensure parent has width
        setTimeout(() => {
          const swiperInstance = new Swiper(el, {
            observer: true,
            observeParents: true,
            observeSlideChildren: true,
            navigation: {
              nextEl: '.swiper-button-next',
              prevEl: '.swiper-button-prev',
            },
            pagination: {
              el: '.swiper-pagination',
              clickable: true,
            },
            slidesPerView: 1,
            spaceBetween: 20,
            loop: false,
            transitionSpeed: 300,
            watchOverflow: true,
            breakpoints: {
              768: {
                slidesPerView: 3,
                spaceBetween: 30
              }
            },
            on: {
              init: function() {
                this.update();
              }
            }
          });

          // Force update after initialization
          setTimeout(() => {
            swiperInstance.update();
          }, 100);
        }, 50);
      });

const swiperVideos = context.querySelectorAll('.video-swiper');

      swiperVideos.forEach(el => {
        const slidesCount = el.querySelectorAll('.swiper-slide').length;
        const mobileCarousel = el.getAttribute('data-mobile-carousel');

        // If carousel_active is false (data-mobile-carousel="disabled"), skip on mobile
        if (mobileCarousel === 'disabled' && window.innerWidth < 768) {
          return; // Don't initialize Swiper on mobile
        }

        // Initialize Swiper with a slight delay to ensure parent has width
        setTimeout(() => {
          const swiperInstance = new Swiper(el, {
            observer: true,
            observeParents: true,
            observeSlideChildren: true,
            navigation: {
              nextEl: '.swiper-button-next',
              prevEl: '.swiper-button-prev',
            },
            pagination: {
              el: '.swiper-pagination',
              clickable: true,
            },
            slidesPerView: 'auto',
            spaceBetween: 20,
            loop: false,
            transitionSpeed: 300,
            watchOverflow: true,
            on: {
              init: function() {
                this.update();
              }
            }
          });

          // Force update after initialization
          setTimeout(() => {
            swiperInstance.update();
          }, 100);
        }, 50);
      });



      }

      fao.initSwiper();
    }
  }
})(jQuery, Drupal);
