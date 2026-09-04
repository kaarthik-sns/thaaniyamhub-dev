/**
 * SPSwiper 10.3.1
 * Most modern mobile touch slider and framework with hardware accelerated transitions
 * https://spswiperjs.com
 *
 * Copyright 2014-2023 Vladimir Kharlampidi
 *
 * Released under the MIT License
 *
 * Released on: September 28, 2023
 */

var SPSwiper = (function () {
  'use strict';

  /**
   * SSR Window 4.0.2
   * Better handling for window object in SSR environment
   * https://github.com/nolimits4web/ssr-window
   *
   * Copyright 2021, Vladimir Kharlampidi
   *
   * Licensed under MIT
   *
   * Released on: December 13, 2021
   */
  /* eslint-disable no-param-reassign */
  function isObject$1(obj) {
    return obj !== null && typeof obj === 'object' && 'constructor' in obj && obj.constructor === Object;
  }
  function extend$1(target, src) {
    if (target === void 0) {
      target = {};
    }
    if (src === void 0) {
      src = {};
    }
    Object.keys(src).forEach(key => {
      if (typeof target[key] === 'undefined') target[key] = src[key];else if (isObject$1(src[key]) && isObject$1(target[key]) && Object.keys(src[key]).length > 0) {
        extend$1(target[key], src[key]);
      }
    });
  }
  const ssrDocument = {
    body: {},
    addEventListener() {},
    removeEventListener() {},
    activeElement: {
      blur() {},
      nodeName: ''
    },
    querySelector() {
      return null;
    },
    querySelectorAll() {
      return [];
    },
    getElementById() {
      return null;
    },
    createEvent() {
      return {
        initEvent() {}
      };
    },
    createElement() {
      return {
        children: [],
        childNodes: [],
        style: {},
        setAttribute() {},
        getElementsByTagName() {
          return [];
        }
      };
    },
    createElementNS() {
      return {};
    },
    importNode() {
      return null;
    },
    location: {
      hash: '',
      host: '',
      hostname: '',
      href: '',
      origin: '',
      pathname: '',
      protocol: '',
      search: ''
    }
  };
  function getDocument() {
    const doc = typeof document !== 'undefined' ? document : {};
    extend$1(doc, ssrDocument);
    return doc;
  }
  const ssrWindow = {
    document: ssrDocument,
    navigator: {
      userAgent: ''
    },
    location: {
      hash: '',
      host: '',
      hostname: '',
      href: '',
      origin: '',
      pathname: '',
      protocol: '',
      search: ''
    },
    history: {
      replaceState() {},
      pushState() {},
      go() {},
      back() {}
    },
    CustomEvent: function CustomEvent() {
      return this;
    },
    addEventListener() {},
    removeEventListener() {},
    getComputedStyle() {
      return {
        getPropertyValue() {
          return '';
        }
      };
    },
    Image() {},
    Date() {},
    screen: {},
    setTimeout() {},
    clearTimeout() {},
    matchMedia() {
      return {};
    },
    requestAnimationFrame(callback) {
      if (typeof setTimeout === 'undefined') {
        callback();
        return null;
      }
      return setTimeout(callback, 0);
    },
    cancelAnimationFrame(id) {
      if (typeof setTimeout === 'undefined') {
        return;
      }
      clearTimeout(id);
    }
  };
  function getWindow() {
    const win = typeof window !== 'undefined' ? window : {};
    extend$1(win, ssrWindow);
    return win;
  }

  function deleteProps(obj) {
    const object = obj;
    Object.keys(object).forEach(key => {
      try {
        object[key] = null;
      } catch (e) {
        // no getter for object
      }
      try {
        delete object[key];
      } catch (e) {
        // something got wrong
      }
    });
  }
  function nextTick(callback, delay) {
    if (delay === void 0) {
      delay = 0;
    }
    return setTimeout(callback, delay);
  }
  function now() {
    return Date.now();
  }
  function getComputedStyle$1(el) {
    const window = getWindow();
    let style;
    if (window.getComputedStyle) {
      style = window.getComputedStyle(el, null);
    }
    if (!style && el.currentStyle) {
      style = el.currentStyle;
    }
    if (!style) {
      style = el.style;
    }
    return style;
  }
  function getTranslate(el, axis) {
    if (axis === void 0) {
      axis = 'x';
    }
    const window = getWindow();
    let matrix;
    let curTransform;
    let transformMatrix;
    const curStyle = getComputedStyle$1(el);
    if (window.WebKitCSSMatrix) {
      curTransform = curStyle.transform || curStyle.webkitTransform;
      if (curTransform.split(',').length > 6) {
        curTransform = curTransform.split(', ').map(a => a.replace(',', '.')).join(', ');
      }
      // Some old versions of Webkit choke when 'none' is passed; pass
      // empty string instead in this case
      transformMatrix = new window.WebKitCSSMatrix(curTransform === 'none' ? '' : curTransform);
    } else {
      transformMatrix = curStyle.MozTransform || curStyle.OTransform || curStyle.MsTransform || curStyle.msTransform || curStyle.transform || curStyle.getPropertyValue('transform').replace('translate(', 'matrix(1, 0, 0, 1,');
      matrix = transformMatrix.toString().split(',');
    }
    if (axis === 'x') {
      // Latest Chrome and webkits Fix
      if (window.WebKitCSSMatrix) curTransform = transformMatrix.m41;
      // Crazy IE10 Matrix
      else if (matrix.length === 16) curTransform = parseFloat(matrix[12]);
      // Normal Browsers
      else curTransform = parseFloat(matrix[4]);
    }
    if (axis === 'y') {
      // Latest Chrome and webkits Fix
      if (window.WebKitCSSMatrix) curTransform = transformMatrix.m42;
      // Crazy IE10 Matrix
      else if (matrix.length === 16) curTransform = parseFloat(matrix[13]);
      // Normal Browsers
      else curTransform = parseFloat(matrix[5]);
    }
    return curTransform || 0;
  }
  function isObject(o) {
    return typeof o === 'object' && o !== null && o.constructor && Object.prototype.toString.call(o).slice(8, -1) === 'Object';
  }
  function isNode(node) {
    // eslint-disable-next-line
    if (typeof window !== 'undefined' && typeof window.HTMLElement !== 'undefined') {
      return node instanceof HTMLElement;
    }
    return node && (node.nodeType === 1 || node.nodeType === 11);
  }
  function extend() {
    const to = Object(arguments.length <= 0 ? undefined : arguments[0]);
    const noExtend = ['__proto__', 'constructor', 'prototype'];
    for (let i = 1; i < arguments.length; i += 1) {
      const nextSource = i < 0 || arguments.length <= i ? undefined : arguments[i];
      if (nextSource !== undefined && nextSource !== null && !isNode(nextSource)) {
        const keysArray = Object.keys(Object(nextSource)).filter(key => noExtend.indexOf(key) < 0);
        for (let nextIndex = 0, len = keysArray.length; nextIndex < len; nextIndex += 1) {
          const nextKey = keysArray[nextIndex];
          const desc = Object.getOwnPropertyDescriptor(nextSource, nextKey);
          if (desc !== undefined && desc.enumerable) {
            if (isObject(to[nextKey]) && isObject(nextSource[nextKey])) {
              if (nextSource[nextKey].__spswiper__) {
                to[nextKey] = nextSource[nextKey];
              } else {
                extend(to[nextKey], nextSource[nextKey]);
              }
            } else if (!isObject(to[nextKey]) && isObject(nextSource[nextKey])) {
              to[nextKey] = {};
              if (nextSource[nextKey].__spswiper__) {
                to[nextKey] = nextSource[nextKey];
              } else {
                extend(to[nextKey], nextSource[nextKey]);
              }
            } else {
              to[nextKey] = nextSource[nextKey];
            }
          }
        }
      }
    }
    return to;
  }
  function setCSSProperty(el, varName, varValue) {
    el.style.setProperty(varName, varValue);
  }
  function animateCSSModeScroll(_ref) {
    let {
      spswiper,
      targetPosition,
      side
    } = _ref;
    const window = getWindow();
    const startPosition = -spswiper.translate;
    let startTime = null;
    let time;
    const duration = spswiper.params.speed;
    spswiper.wrapperEl.style.scrollSnapType = 'none';
    window.cancelAnimationFrame(spswiper.cssModeFrameID);
    const dir = targetPosition > startPosition ? 'next' : 'prev';
    const isOutOfBound = (current, target) => {
      return dir === 'next' && current >= target || dir === 'prev' && current <= target;
    };
    const animate = () => {
      time = new Date().getTime();
      if (startTime === null) {
        startTime = time;
      }
      const progress = Math.max(Math.min((time - startTime) / duration, 1), 0);
      const easeProgress = 0.5 - Math.cos(progress * Math.PI) / 2;
      let currentPosition = startPosition + easeProgress * (targetPosition - startPosition);
      if (isOutOfBound(currentPosition, targetPosition)) {
        currentPosition = targetPosition;
      }
      spswiper.wrapperEl.scrollTo({
        [side]: currentPosition
      });
      if (isOutOfBound(currentPosition, targetPosition)) {
        spswiper.wrapperEl.style.overflow = 'hidden';
        spswiper.wrapperEl.style.scrollSnapType = '';
        setTimeout(() => {
          spswiper.wrapperEl.style.overflow = '';
          spswiper.wrapperEl.scrollTo({
            [side]: currentPosition
          });
        });
        window.cancelAnimationFrame(spswiper.cssModeFrameID);
        return;
      }
      spswiper.cssModeFrameID = window.requestAnimationFrame(animate);
    };
    animate();
  }
  function getSlideTransformEl(slideEl) {
    return slideEl.querySelector('.spswiper-slide-transform') || slideEl.shadowRoot && slideEl.shadowRoot.querySelector('.spswiper-slide-transform') || slideEl;
  }
  function elementChildren(element, selector) {
    if (selector === void 0) {
      selector = '';
    }
    return [...element.children].filter(el => el.matches(selector));
  }
  function createElement(tag, classes) {
    if (classes === void 0) {
      classes = [];
    }
    const el = document.createElement(tag);
    el.classList.add(...(Array.isArray(classes) ? classes : [classes]));
    return el;
  }
  function elementOffset(el) {
    const window = getWindow();
    const document = getDocument();
    const box = el.getBoundingClientRect();
    const body = document.body;
    const clientTop = el.clientTop || body.clientTop || 0;
    const clientLeft = el.clientLeft || body.clientLeft || 0;
    const scrollTop = el === window ? window.scrollY : el.scrollTop;
    const scrollLeft = el === window ? window.scrollX : el.scrollLeft;
    return {
      top: box.top + scrollTop - clientTop,
      left: box.left + scrollLeft - clientLeft
    };
  }
  function elementPrevAll(el, selector) {
    const prevEls = [];
    while (el.previousElementSibling) {
      const prev = el.previousElementSibling; // eslint-disable-line
      if (selector) {
        if (prev.matches(selector)) prevEls.push(prev);
      } else prevEls.push(prev);
      el = prev;
    }
    return prevEls;
  }
  function elementNextAll(el, selector) {
    const nextEls = [];
    while (el.nextElementSibling) {
      const next = el.nextElementSibling; // eslint-disable-line
      if (selector) {
        if (next.matches(selector)) nextEls.push(next);
      } else nextEls.push(next);
      el = next;
    }
    return nextEls;
  }
  function elementStyle(el, prop) {
    const window = getWindow();
    return window.getComputedStyle(el, null).getPropertyValue(prop);
  }
  function elementIndex(el) {
    let child = el;
    let i;
    if (child) {
      i = 0;
      // eslint-disable-next-line
      while ((child = child.previousSibling) !== null) {
        if (child.nodeType === 1) i += 1;
      }
      return i;
    }
    return undefined;
  }
  function elementParents(el, selector) {
    const parents = []; // eslint-disable-line
    let parent = el.parentElement; // eslint-disable-line
    while (parent) {
      if (selector) {
        if (parent.matches(selector)) parents.push(parent);
      } else {
        parents.push(parent);
      }
      parent = parent.parentElement;
    }
    return parents;
  }
  function elementTransitionEnd(el, callback) {
    function fireCallBack(e) {
      if (e.target !== el) return;
      callback.call(el, e);
      el.removeEventListener('transitionend', fireCallBack);
    }
    if (callback) {
      el.addEventListener('transitionend', fireCallBack);
    }
  }
  function elementOuterSize(el, size, includeMargins) {
    const window = getWindow();
    if (includeMargins) {
      return el[size === 'width' ? 'offsetWidth' : 'offsetHeight'] + parseFloat(window.getComputedStyle(el, null).getPropertyValue(size === 'width' ? 'margin-right' : 'margin-top')) + parseFloat(window.getComputedStyle(el, null).getPropertyValue(size === 'width' ? 'margin-left' : 'margin-bottom'));
    }
    return el.offsetWidth;
  }

  let support;
  function calcSupport() {
    const window = getWindow();
    const document = getDocument();
    return {
      smoothScroll: document.documentElement && document.documentElement.style && 'scrollBehavior' in document.documentElement.style,
      touch: !!('ontouchstart' in window || window.DocumentTouch && document instanceof window.DocumentTouch)
    };
  }
  function getSupport() {
    if (!support) {
      support = calcSupport();
    }
    return support;
  }

  let deviceCached;
  function calcDevice(_temp) {
    let {
      userAgent
    } = _temp === void 0 ? {} : _temp;
    const support = getSupport();
    const window = getWindow();
    const platform = window.navigator.platform;
    const ua = userAgent || window.navigator.userAgent;
    const device = {
      ios: false,
      android: false
    };
    const screenWidth = window.screen.width;
    const screenHeight = window.screen.height;
    const android = ua.match(/(Android);?[\s\/]+([\d.]+)?/); // eslint-disable-line
    let ipad = ua.match(/(iPad).*OS\s([\d_]+)/);
    const ipod = ua.match(/(iPod)(.*OS\s([\d_]+))?/);
    const iphone = !ipad && ua.match(/(iPhone\sOS|iOS)\s([\d_]+)/);
    const windows = platform === 'Win32';
    let macos = platform === 'MacIntel';

    // iPadOs 13 fix
    const iPadScreens = ['1024x1366', '1366x1024', '834x1194', '1194x834', '834x1112', '1112x834', '768x1024', '1024x768', '820x1180', '1180x820', '810x1080', '1080x810'];
    if (!ipad && macos && support.touch && iPadScreens.indexOf(`${screenWidth}x${screenHeight}`) >= 0) {
      ipad = ua.match(/(Version)\/([\d.]+)/);
      if (!ipad) ipad = [0, 1, '13_0_0'];
      macos = false;
    }

    // Android
    if (android && !windows) {
      device.os = 'android';
      device.android = true;
    }
    if (ipad || iphone || ipod) {
      device.os = 'ios';
      device.ios = true;
    }

    // Export object
    return device;
  }
  function getDevice(overrides) {
    if (overrides === void 0) {
      overrides = {};
    }
    if (!deviceCached) {
      deviceCached = calcDevice(overrides);
    }
    return deviceCached;
  }

  let browser;
  function calcBrowser() {
    const window = getWindow();
    let needPerspectiveFix = false;
    function isSafari() {
      const ua = window.navigator.userAgent.toLowerCase();
      return ua.indexOf('safari') >= 0 && ua.indexOf('chrome') < 0 && ua.indexOf('android') < 0;
    }
    if (isSafari()) {
      const ua = String(window.navigator.userAgent);
      if (ua.includes('Version/')) {
        const [major, minor] = ua.split('Version/')[1].split(' ')[0].split('.').map(num => Number(num));
        needPerspectiveFix = major < 16 || major === 16 && minor < 2;
      }
    }
    return {
      isSafari: needPerspectiveFix || isSafari(),
      needPerspectiveFix,
      isWebView: /(iPhone|iPod|iPad).*AppleWebKit(?!.*Safari)/i.test(window.navigator.userAgent)
    };
  }
  function getBrowser() {
    if (!browser) {
      browser = calcBrowser();
    }
    return browser;
  }

  function Resize(_ref) {
    let {
      spswiper,
      on,
      emit
    } = _ref;
    const window = getWindow();
    let observer = null;
    let animationFrame = null;
    const resizeHandler = () => {
      if (!spswiper || spswiper.destroyed || !spswiper.initialized) return;
      emit('beforeResize');
      emit('resize');
    };
    const createObserver = () => {
      if (!spswiper || spswiper.destroyed || !spswiper.initialized) return;
      observer = new ResizeObserver(entries => {
        animationFrame = window.requestAnimationFrame(() => {
          const {
            width,
            height
          } = spswiper;
          let newWidth = width;
          let newHeight = height;
          entries.forEach(_ref2 => {
            let {
              contentBoxSize,
              contentRect,
              target
            } = _ref2;
            if (target && target !== spswiper.el) return;
            newWidth = contentRect ? contentRect.width : (contentBoxSize[0] || contentBoxSize).inlineSize;
            newHeight = contentRect ? contentRect.height : (contentBoxSize[0] || contentBoxSize).blockSize;
          });
          if (newWidth !== width || newHeight !== height) {
            resizeHandler();
          }
        });
      });
      observer.observe(spswiper.el);
    };
    const removeObserver = () => {
      if (animationFrame) {
        window.cancelAnimationFrame(animationFrame);
      }
      if (observer && observer.unobserve && spswiper.el) {
        observer.unobserve(spswiper.el);
        observer = null;
      }
    };
    const orientationChangeHandler = () => {
      if (!spswiper || spswiper.destroyed || !spswiper.initialized) return;
      emit('orientationchange');
    };
    on('init', () => {
      if (spswiper.params.resizeObserver && typeof window.ResizeObserver !== 'undefined') {
        createObserver();
        return;
      }
      window.addEventListener('resize', resizeHandler);
      window.addEventListener('orientationchange', orientationChangeHandler);
    });
    on('destroy', () => {
      removeObserver();
      window.removeEventListener('resize', resizeHandler);
      window.removeEventListener('orientationchange', orientationChangeHandler);
    });
  }

  function Observer(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit
    } = _ref;
    const observers = [];
    const window = getWindow();
    const attach = function (target, options) {
      if (options === void 0) {
        options = {};
      }
      const ObserverFunc = window.MutationObserver || window.WebkitMutationObserver;
      const observer = new ObserverFunc(mutations => {
        // The observerUpdate event should only be triggered
        // once despite the number of mutations.  Additional
        // triggers are redundant and are very costly
        if (spswiper.__preventObserver__) return;
        if (mutations.length === 1) {
          emit('observerUpdate', mutations[0]);
          return;
        }
        const observerUpdate = function observerUpdate() {
          emit('observerUpdate', mutations[0]);
        };
        if (window.requestAnimationFrame) {
          window.requestAnimationFrame(observerUpdate);
        } else {
          window.setTimeout(observerUpdate, 0);
        }
      });
      observer.observe(target, {
        attributes: typeof options.attributes === 'undefined' ? true : options.attributes,
        childList: typeof options.childList === 'undefined' ? true : options.childList,
        characterData: typeof options.characterData === 'undefined' ? true : options.characterData
      });
      observers.push(observer);
    };
    const init = () => {
      if (!spswiper.params.observer) return;
      if (spswiper.params.observeParents) {
        const containerParents = elementParents(spswiper.hostEl);
        for (let i = 0; i < containerParents.length; i += 1) {
          attach(containerParents[i]);
        }
      }
      // Observe container
      attach(spswiper.hostEl, {
        childList: spswiper.params.observeSlideChildren
      });

      // Observe wrapper
      attach(spswiper.wrapperEl, {
        attributes: false
      });
    };
    const destroy = () => {
      observers.forEach(observer => {
        observer.disconnect();
      });
      observers.splice(0, observers.length);
    };
    extendParams({
      observer: false,
      observeParents: false,
      observeSlideChildren: false
    });
    on('init', init);
    on('destroy', destroy);
  }

  /* eslint-disable no-underscore-dangle */

  var eventsEmitter = {
    on(events, handler, priority) {
      const self = this;
      if (!self.eventsListeners || self.destroyed) return self;
      if (typeof handler !== 'function') return self;
      const method = priority ? 'unshift' : 'push';
      events.split(' ').forEach(event => {
        if (!self.eventsListeners[event]) self.eventsListeners[event] = [];
        self.eventsListeners[event][method](handler);
      });
      return self;
    },
    once(events, handler, priority) {
      const self = this;
      if (!self.eventsListeners || self.destroyed) return self;
      if (typeof handler !== 'function') return self;
      function onceHandler() {
        self.off(events, onceHandler);
        if (onceHandler.__emitterProxy) {
          delete onceHandler.__emitterProxy;
        }
        for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
          args[_key] = arguments[_key];
        }
        handler.apply(self, args);
      }
      onceHandler.__emitterProxy = handler;
      return self.on(events, onceHandler, priority);
    },
    onAny(handler, priority) {
      const self = this;
      if (!self.eventsListeners || self.destroyed) return self;
      if (typeof handler !== 'function') return self;
      const method = priority ? 'unshift' : 'push';
      if (self.eventsAnyListeners.indexOf(handler) < 0) {
        self.eventsAnyListeners[method](handler);
      }
      return self;
    },
    offAny(handler) {
      const self = this;
      if (!self.eventsListeners || self.destroyed) return self;
      if (!self.eventsAnyListeners) return self;
      const index = self.eventsAnyListeners.indexOf(handler);
      if (index >= 0) {
        self.eventsAnyListeners.splice(index, 1);
      }
      return self;
    },
    off(events, handler) {
      const self = this;
      if (!self.eventsListeners || self.destroyed) return self;
      if (!self.eventsListeners) return self;
      events.split(' ').forEach(event => {
        if (typeof handler === 'undefined') {
          self.eventsListeners[event] = [];
        } else if (self.eventsListeners[event]) {
          self.eventsListeners[event].forEach((eventHandler, index) => {
            if (eventHandler === handler || eventHandler.__emitterProxy && eventHandler.__emitterProxy === handler) {
              self.eventsListeners[event].splice(index, 1);
            }
          });
        }
      });
      return self;
    },
    emit() {
      const self = this;
      if (!self.eventsListeners || self.destroyed) return self;
      if (!self.eventsListeners) return self;
      let events;
      let data;
      let context;
      for (var _len2 = arguments.length, args = new Array(_len2), _key2 = 0; _key2 < _len2; _key2++) {
        args[_key2] = arguments[_key2];
      }
      if (typeof args[0] === 'string' || Array.isArray(args[0])) {
        events = args[0];
        data = args.slice(1, args.length);
        context = self;
      } else {
        events = args[0].events;
        data = args[0].data;
        context = args[0].context || self;
      }
      data.unshift(context);
      const eventsArray = Array.isArray(events) ? events : events.split(' ');
      eventsArray.forEach(event => {
        if (self.eventsAnyListeners && self.eventsAnyListeners.length) {
          self.eventsAnyListeners.forEach(eventHandler => {
            eventHandler.apply(context, [event, ...data]);
          });
        }
        if (self.eventsListeners && self.eventsListeners[event]) {
          self.eventsListeners[event].forEach(eventHandler => {
            eventHandler.apply(context, data);
          });
        }
      });
      return self;
    }
  };

  function updateSize() {
    const spswiper = this;
    let width;
    let height;
    const el = spswiper.el;
    if (typeof spswiper.params.width !== 'undefined' && spswiper.params.width !== null) {
      width = spswiper.params.width;
    } else {
      width = el.clientWidth;
    }
    if (typeof spswiper.params.height !== 'undefined' && spswiper.params.height !== null) {
      height = spswiper.params.height;
    } else {
      height = el.clientHeight;
    }
    if (width === 0 && spswiper.isHorizontal() || height === 0 && spswiper.isVertical()) {
      return;
    }

    // Subtract paddings
    width = width - parseInt(elementStyle(el, 'padding-left') || 0, 10) - parseInt(elementStyle(el, 'padding-right') || 0, 10);
    height = height - parseInt(elementStyle(el, 'padding-top') || 0, 10) - parseInt(elementStyle(el, 'padding-bottom') || 0, 10);
    if (Number.isNaN(width)) width = 0;
    if (Number.isNaN(height)) height = 0;
    Object.assign(spswiper, {
      width,
      height,
      size: spswiper.isHorizontal() ? width : height
    });
  }

  function updateSlides() {
    const spswiper = this;
    function getDirectionLabel(property) {
      if (spswiper.isHorizontal()) {
        return property;
      }
      // prettier-ignore
      return {
        'width': 'height',
        'margin-top': 'margin-left',
        'margin-bottom ': 'margin-right',
        'margin-left': 'margin-top',
        'margin-right': 'margin-bottom',
        'padding-left': 'padding-top',
        'padding-right': 'padding-bottom',
        'marginRight': 'marginBottom'
      }[property];
    }
    function getDirectionPropertyValue(node, label) {
      return parseFloat(node.getPropertyValue(getDirectionLabel(label)) || 0);
    }
    const params = spswiper.params;
    const {
      wrapperEl,
      slidesEl,
      size: spswiperSize,
      rtlTranslate: rtl,
      wrongRTL
    } = spswiper;
    const isVirtual = spswiper.virtual && params.virtual.enabled;
    const previousSlidesLength = isVirtual ? spswiper.virtual.slides.length : spswiper.slides.length;
    const slides = elementChildren(slidesEl, `.${spswiper.params.slideClass}, spswiper-slide`);
    const slidesLength = isVirtual ? spswiper.virtual.slides.length : slides.length;
    let snapGrid = [];
    const slidesGrid = [];
    const slidesSizesGrid = [];
    let offsetBefore = params.slidesOffsetBefore;
    if (typeof offsetBefore === 'function') {
      offsetBefore = params.slidesOffsetBefore.call(spswiper);
    }
    let offsetAfter = params.slidesOffsetAfter;
    if (typeof offsetAfter === 'function') {
      offsetAfter = params.slidesOffsetAfter.call(spswiper);
    }
    const previousSnapGridLength = spswiper.snapGrid.length;
    const previousSlidesGridLength = spswiper.slidesGrid.length;
    let spaceBetween = params.spaceBetween;
    let slidePosition = -offsetBefore;
    let prevSlideSize = 0;
    let index = 0;
    if (typeof spswiperSize === 'undefined') {
      return;
    }
    if (typeof spaceBetween === 'string' && spaceBetween.indexOf('%') >= 0) {
      spaceBetween = parseFloat(spaceBetween.replace('%', '')) / 100 * spswiperSize;
    } else if (typeof spaceBetween === 'string') {
      spaceBetween = parseFloat(spaceBetween);
    }
    spswiper.virtualSize = -spaceBetween;

    // reset margins
    slides.forEach(slideEl => {
      if (rtl) {
        slideEl.style.marginLeft = '';
      } else {
        slideEl.style.marginRight = '';
      }
      slideEl.style.marginBottom = '';
      slideEl.style.marginTop = '';
    });

    // reset cssMode offsets
    if (params.centeredSlides && params.cssMode) {
      setCSSProperty(wrapperEl, '--spswiper-centered-offset-before', '');
      setCSSProperty(wrapperEl, '--spswiper-centered-offset-after', '');
    }
    const gridEnabled = params.grid && params.grid.rows > 1 && spswiper.grid;
    if (gridEnabled) {
      spswiper.grid.initSlides(slidesLength);
    }

    // Calc slides
    let slideSize;
    const shouldResetSlideSize = params.slidesPerView === 'auto' && params.breakpoints && Object.keys(params.breakpoints).filter(key => {
      return typeof params.breakpoints[key].slidesPerView !== 'undefined';
    }).length > 0;
    for (let i = 0; i < slidesLength; i += 1) {
      slideSize = 0;
      let slide;
      if (slides[i]) slide = slides[i];
      if (gridEnabled) {
        spswiper.grid.updateSlide(i, slide, slidesLength, getDirectionLabel);
      }
      if (slides[i] && elementStyle(slide, 'display') === 'none') continue; // eslint-disable-line

      if (params.slidesPerView === 'auto') {
        if (shouldResetSlideSize) {
          slides[i].style[getDirectionLabel('width')] = ``;
        }
        const slideStyles = getComputedStyle(slide);
        const currentTransform = slide.style.transform;
        const currentWebKitTransform = slide.style.webkitTransform;
        if (currentTransform) {
          slide.style.transform = 'none';
        }
        if (currentWebKitTransform) {
          slide.style.webkitTransform = 'none';
        }
        if (params.roundLengths) {
          slideSize = spswiper.isHorizontal() ? elementOuterSize(slide, 'width', true) : elementOuterSize(slide, 'height', true);
        } else {
          // eslint-disable-next-line
          const width = getDirectionPropertyValue(slideStyles, 'width');
          const paddingLeft = getDirectionPropertyValue(slideStyles, 'padding-left');
          const paddingRight = getDirectionPropertyValue(slideStyles, 'padding-right');
          const marginLeft = getDirectionPropertyValue(slideStyles, 'margin-left');
          const marginRight = getDirectionPropertyValue(slideStyles, 'margin-right');
          const boxSizing = slideStyles.getPropertyValue('box-sizing');
          if (boxSizing && boxSizing === 'border-box') {
            slideSize = width + marginLeft + marginRight;
          } else {
            const {
              clientWidth,
              offsetWidth
            } = slide;
            slideSize = width + paddingLeft + paddingRight + marginLeft + marginRight + (offsetWidth - clientWidth);
          }
        }
        if (currentTransform) {
          slide.style.transform = currentTransform;
        }
        if (currentWebKitTransform) {
          slide.style.webkitTransform = currentWebKitTransform;
        }
        if (params.roundLengths) slideSize = Math.floor(slideSize);
      } else {
        slideSize = (spswiperSize - (params.slidesPerView - 1) * spaceBetween) / params.slidesPerView;
        if (params.roundLengths) slideSize = Math.floor(slideSize);
        if (slides[i]) {
          slides[i].style[getDirectionLabel('width')] = `${slideSize}px`;
        }
      }
      if (slides[i]) {
        slides[i].spswiperSlideSize = slideSize;
      }
      slidesSizesGrid.push(slideSize);
      if (params.centeredSlides) {
        slidePosition = slidePosition + slideSize / 2 + prevSlideSize / 2 + spaceBetween;
        if (prevSlideSize === 0 && i !== 0) slidePosition = slidePosition - spswiperSize / 2 - spaceBetween;
        if (i === 0) slidePosition = slidePosition - spswiperSize / 2 - spaceBetween;
        if (Math.abs(slidePosition) < 1 / 1000) slidePosition = 0;
        if (params.roundLengths) slidePosition = Math.floor(slidePosition);
        if (index % params.slidesPerGroup === 0) snapGrid.push(slidePosition);
        slidesGrid.push(slidePosition);
      } else {
        if (params.roundLengths) slidePosition = Math.floor(slidePosition);
        if ((index - Math.min(spswiper.params.slidesPerGroupSkip, index)) % spswiper.params.slidesPerGroup === 0) snapGrid.push(slidePosition);
        slidesGrid.push(slidePosition);
        slidePosition = slidePosition + slideSize + spaceBetween;
      }
      spswiper.virtualSize += slideSize + spaceBetween;
      prevSlideSize = slideSize;
      index += 1;
    }
    spswiper.virtualSize = Math.max(spswiper.virtualSize, spswiperSize) + offsetAfter;
    if (rtl && wrongRTL && (params.effect === 'slide' || params.effect === 'coverflow')) {
      wrapperEl.style.width = `${spswiper.virtualSize + spaceBetween}px`;
    }
    if (params.setWrapperSize) {
      wrapperEl.style[getDirectionLabel('width')] = `${spswiper.virtualSize + spaceBetween}px`;
    }
    if (gridEnabled) {
      spswiper.grid.updateWrapperSize(slideSize, snapGrid, getDirectionLabel);
    }

    // Remove last grid elements depending on width
    if (!params.centeredSlides) {
      const newSlidesGrid = [];
      for (let i = 0; i < snapGrid.length; i += 1) {
        let slidesGridItem = snapGrid[i];
        if (params.roundLengths) slidesGridItem = Math.floor(slidesGridItem);
        if (snapGrid[i] <= spswiper.virtualSize - spswiperSize) {
          newSlidesGrid.push(slidesGridItem);
        }
      }
      snapGrid = newSlidesGrid;
      if (Math.floor(spswiper.virtualSize - spswiperSize) - Math.floor(snapGrid[snapGrid.length - 1]) > 1) {
        snapGrid.push(spswiper.virtualSize - spswiperSize);
      }
    }
    if (isVirtual && params.loop) {
      const size = slidesSizesGrid[0] + spaceBetween;
      if (params.slidesPerGroup > 1) {
        const groups = Math.ceil((spswiper.virtual.slidesBefore + spswiper.virtual.slidesAfter) / params.slidesPerGroup);
        const groupSize = size * params.slidesPerGroup;
        for (let i = 0; i < groups; i += 1) {
          snapGrid.push(snapGrid[snapGrid.length - 1] + groupSize);
        }
      }
      for (let i = 0; i < spswiper.virtual.slidesBefore + spswiper.virtual.slidesAfter; i += 1) {
        if (params.slidesPerGroup === 1) {
          snapGrid.push(snapGrid[snapGrid.length - 1] + size);
        }
        slidesGrid.push(slidesGrid[slidesGrid.length - 1] + size);
        spswiper.virtualSize += size;
      }
    }
    if (snapGrid.length === 0) snapGrid = [0];
    if (spaceBetween !== 0) {
      const key = spswiper.isHorizontal() && rtl ? 'marginLeft' : getDirectionLabel('marginRight');
      slides.filter((_, slideIndex) => {
        if (!params.cssMode || params.loop) return true;
        if (slideIndex === slides.length - 1) {
          return false;
        }
        return true;
      }).forEach(slideEl => {
        slideEl.style[key] = `${spaceBetween}px`;
      });
    }
    if (params.centeredSlides && params.centeredSlidesBounds) {
      let allSlidesSize = 0;
      slidesSizesGrid.forEach(slideSizeValue => {
        allSlidesSize += slideSizeValue + (spaceBetween || 0);
      });
      allSlidesSize -= spaceBetween;
      const maxSnap = allSlidesSize - spswiperSize;
      snapGrid = snapGrid.map(snap => {
        if (snap <= 0) return -offsetBefore;
        if (snap > maxSnap) return maxSnap + offsetAfter;
        return snap;
      });
    }
    if (params.centerInsufficientSlides) {
      let allSlidesSize = 0;
      slidesSizesGrid.forEach(slideSizeValue => {
        allSlidesSize += slideSizeValue + (spaceBetween || 0);
      });
      allSlidesSize -= spaceBetween;
      if (allSlidesSize < spswiperSize) {
        const allSlidesOffset = (spswiperSize - allSlidesSize) / 2;
        snapGrid.forEach((snap, snapIndex) => {
          snapGrid[snapIndex] = snap - allSlidesOffset;
        });
        slidesGrid.forEach((snap, snapIndex) => {
          slidesGrid[snapIndex] = snap + allSlidesOffset;
        });
      }
    }
    Object.assign(spswiper, {
      slides,
      snapGrid,
      slidesGrid,
      slidesSizesGrid
    });
    if (params.centeredSlides && params.cssMode && !params.centeredSlidesBounds) {
      setCSSProperty(wrapperEl, '--spswiper-centered-offset-before', `${-snapGrid[0]}px`);
      setCSSProperty(wrapperEl, '--spswiper-centered-offset-after', `${spswiper.size / 2 - slidesSizesGrid[slidesSizesGrid.length - 1] / 2}px`);
      const addToSnapGrid = -spswiper.snapGrid[0];
      const addToSlidesGrid = -spswiper.slidesGrid[0];
      spswiper.snapGrid = spswiper.snapGrid.map(v => v + addToSnapGrid);
      spswiper.slidesGrid = spswiper.slidesGrid.map(v => v + addToSlidesGrid);
    }
    if (slidesLength !== previousSlidesLength) {
      spswiper.emit('slidesLengthChange');
    }
    if (snapGrid.length !== previousSnapGridLength) {
      if (spswiper.params.watchOverflow) spswiper.checkOverflow();
      spswiper.emit('snapGridLengthChange');
    }
    if (slidesGrid.length !== previousSlidesGridLength) {
      spswiper.emit('slidesGridLengthChange');
    }
    if (params.watchSlidesProgress) {
      spswiper.updateSlidesOffset();
    }
    if (!isVirtual && !params.cssMode && (params.effect === 'slide' || params.effect === 'fade')) {
      const backFaceHiddenClass = `${params.containerModifierClass}backface-hidden`;
      const hasClassBackfaceClassAdded = spswiper.el.classList.contains(backFaceHiddenClass);
      if (slidesLength <= params.maxBackfaceHiddenSlides) {
        if (!hasClassBackfaceClassAdded) spswiper.el.classList.add(backFaceHiddenClass);
      } else if (hasClassBackfaceClassAdded) {
        spswiper.el.classList.remove(backFaceHiddenClass);
      }
    }
  }

  function updateAutoHeight(speed) {
    const spswiper = this;
    const activeSlides = [];
    const isVirtual = spswiper.virtual && spswiper.params.virtual.enabled;
    let newHeight = 0;
    let i;
    if (typeof speed === 'number') {
      spswiper.setTransition(speed);
    } else if (speed === true) {
      spswiper.setTransition(spswiper.params.speed);
    }
    const getSlideByIndex = index => {
      if (isVirtual) {
        return spswiper.slides[spswiper.getSlideIndexByData(index)];
      }
      return spswiper.slides[index];
    };
    // Find slides currently in view
    if (spswiper.params.slidesPerView !== 'auto' && spswiper.params.slidesPerView > 1) {
      if (spswiper.params.centeredSlides) {
        (spswiper.visibleSlides || []).forEach(slide => {
          activeSlides.push(slide);
        });
      } else {
        for (i = 0; i < Math.ceil(spswiper.params.slidesPerView); i += 1) {
          const index = spswiper.activeIndex + i;
          if (index > spswiper.slides.length && !isVirtual) break;
          activeSlides.push(getSlideByIndex(index));
        }
      }
    } else {
      activeSlides.push(getSlideByIndex(spswiper.activeIndex));
    }

    // Find new height from highest slide in view
    for (i = 0; i < activeSlides.length; i += 1) {
      if (typeof activeSlides[i] !== 'undefined') {
        const height = activeSlides[i].offsetHeight;
        newHeight = height > newHeight ? height : newHeight;
      }
    }

    // Update Height
    if (newHeight || newHeight === 0) spswiper.wrapperEl.style.height = `${newHeight}px`;
  }

  function updateSlidesOffset() {
    const spswiper = this;
    const slides = spswiper.slides;
    // eslint-disable-next-line
    const minusOffset = spswiper.isElement ? spswiper.isHorizontal() ? spswiper.wrapperEl.offsetLeft : spswiper.wrapperEl.offsetTop : 0;
    for (let i = 0; i < slides.length; i += 1) {
      slides[i].spswiperSlideOffset = (spswiper.isHorizontal() ? slides[i].offsetLeft : slides[i].offsetTop) - minusOffset - spswiper.cssOverflowAdjustment();
    }
  }

  function updateSlidesProgress(translate) {
    if (translate === void 0) {
      translate = this && this.translate || 0;
    }
    const spswiper = this;
    const params = spswiper.params;
    const {
      slides,
      rtlTranslate: rtl,
      snapGrid
    } = spswiper;
    if (slides.length === 0) return;
    if (typeof slides[0].spswiperSlideOffset === 'undefined') spswiper.updateSlidesOffset();
    let offsetCenter = -translate;
    if (rtl) offsetCenter = translate;

    // Visible Slides
    slides.forEach(slideEl => {
      slideEl.classList.remove(params.slideVisibleClass);
    });
    spswiper.visibleSlidesIndexes = [];
    spswiper.visibleSlides = [];
    let spaceBetween = params.spaceBetween;
    if (typeof spaceBetween === 'string' && spaceBetween.indexOf('%') >= 0) {
      spaceBetween = parseFloat(spaceBetween.replace('%', '')) / 100 * spswiper.size;
    } else if (typeof spaceBetween === 'string') {
      spaceBetween = parseFloat(spaceBetween);
    }
    for (let i = 0; i < slides.length; i += 1) {
      const slide = slides[i];
      let slideOffset = slide.spswiperSlideOffset;
      if (params.cssMode && params.centeredSlides) {
        slideOffset -= slides[0].spswiperSlideOffset;
      }
      const slideProgress = (offsetCenter + (params.centeredSlides ? spswiper.minTranslate() : 0) - slideOffset) / (slide.spswiperSlideSize + spaceBetween);
      const originalSlideProgress = (offsetCenter - snapGrid[0] + (params.centeredSlides ? spswiper.minTranslate() : 0) - slideOffset) / (slide.spswiperSlideSize + spaceBetween);
      const slideBefore = -(offsetCenter - slideOffset);
      const slideAfter = slideBefore + spswiper.slidesSizesGrid[i];
      const isVisible = slideBefore >= 0 && slideBefore < spswiper.size - 1 || slideAfter > 1 && slideAfter <= spswiper.size || slideBefore <= 0 && slideAfter >= spswiper.size;
      if (isVisible) {
        spswiper.visibleSlides.push(slide);
        spswiper.visibleSlidesIndexes.push(i);
        slides[i].classList.add(params.slideVisibleClass);
      }
      slide.progress = rtl ? -slideProgress : slideProgress;
      slide.originalProgress = rtl ? -originalSlideProgress : originalSlideProgress;
    }
  }

  function updateProgress(translate) {
    const spswiper = this;
    if (typeof translate === 'undefined') {
      const multiplier = spswiper.rtlTranslate ? -1 : 1;
      // eslint-disable-next-line
      translate = spswiper && spswiper.translate && spswiper.translate * multiplier || 0;
    }
    const params = spswiper.params;
    const translatesDiff = spswiper.maxTranslate() - spswiper.minTranslate();
    let {
      progress,
      isBeginning,
      isEnd,
      progressLoop
    } = spswiper;
    const wasBeginning = isBeginning;
    const wasEnd = isEnd;
    if (translatesDiff === 0) {
      progress = 0;
      isBeginning = true;
      isEnd = true;
    } else {
      progress = (translate - spswiper.minTranslate()) / translatesDiff;
      const isBeginningRounded = Math.abs(translate - spswiper.minTranslate()) < 1;
      const isEndRounded = Math.abs(translate - spswiper.maxTranslate()) < 1;
      isBeginning = isBeginningRounded || progress <= 0;
      isEnd = isEndRounded || progress >= 1;
      if (isBeginningRounded) progress = 0;
      if (isEndRounded) progress = 1;
    }
    if (params.loop) {
      const firstSlideIndex = spswiper.getSlideIndexByData(0);
      const lastSlideIndex = spswiper.getSlideIndexByData(spswiper.slides.length - 1);
      const firstSlideTranslate = spswiper.slidesGrid[firstSlideIndex];
      const lastSlideTranslate = spswiper.slidesGrid[lastSlideIndex];
      const translateMax = spswiper.slidesGrid[spswiper.slidesGrid.length - 1];
      const translateAbs = Math.abs(translate);
      if (translateAbs >= firstSlideTranslate) {
        progressLoop = (translateAbs - firstSlideTranslate) / translateMax;
      } else {
        progressLoop = (translateAbs + translateMax - lastSlideTranslate) / translateMax;
      }
      if (progressLoop > 1) progressLoop -= 1;
    }
    Object.assign(spswiper, {
      progress,
      progressLoop,
      isBeginning,
      isEnd
    });
    if (params.watchSlidesProgress || params.centeredSlides && params.autoHeight) spswiper.updateSlidesProgress(translate);
    if (isBeginning && !wasBeginning) {
      spswiper.emit('reachBeginning toEdge');
    }
    if (isEnd && !wasEnd) {
      spswiper.emit('reachEnd toEdge');
    }
    if (wasBeginning && !isBeginning || wasEnd && !isEnd) {
      spswiper.emit('fromEdge');
    }
    spswiper.emit('progress', progress);
  }

  function updateSlidesClasses() {
    const spswiper = this;
    const {
      slides,
      params,
      slidesEl,
      activeIndex
    } = spswiper;
    const isVirtual = spswiper.virtual && params.virtual.enabled;
    const getFilteredSlide = selector => {
      return elementChildren(slidesEl, `.${params.slideClass}${selector}, spswiper-slide${selector}`)[0];
    };
    slides.forEach(slideEl => {
      slideEl.classList.remove(params.slideActiveClass, params.slideNextClass, params.slidePrevClass);
    });
    let activeSlide;
    if (isVirtual) {
      if (params.loop) {
        let slideIndex = activeIndex - spswiper.virtual.slidesBefore;
        if (slideIndex < 0) slideIndex = spswiper.virtual.slides.length + slideIndex;
        if (slideIndex >= spswiper.virtual.slides.length) slideIndex -= spswiper.virtual.slides.length;
        activeSlide = getFilteredSlide(`[data-spswiper-slide-index="${slideIndex}"]`);
      } else {
        activeSlide = getFilteredSlide(`[data-spswiper-slide-index="${activeIndex}"]`);
      }
    } else {
      activeSlide = slides[activeIndex];
    }
    if (activeSlide) {
      // Active classes
      activeSlide.classList.add(params.slideActiveClass);

      // Next Slide
      let nextSlide = elementNextAll(activeSlide, `.${params.slideClass}, spswiper-slide`)[0];
      if (params.loop && !nextSlide) {
        nextSlide = slides[0];
      }
      if (nextSlide) {
        nextSlide.classList.add(params.slideNextClass);
      }
      // Prev Slide
      let prevSlide = elementPrevAll(activeSlide, `.${params.slideClass}, spswiper-slide`)[0];
      if (params.loop && !prevSlide === 0) {
        prevSlide = slides[slides.length - 1];
      }
      if (prevSlide) {
        prevSlide.classList.add(params.slidePrevClass);
      }
    }
    spswiper.emitSlidesClasses();
  }

  const processLazyPreloader = (spswiper, imageEl) => {
    if (!spswiper || spswiper.destroyed || !spswiper.params) return;
    const slideSelector = () => spswiper.isElement ? `spswiper-slide` : `.${spswiper.params.slideClass}`;
    const slideEl = imageEl.closest(slideSelector());
    if (slideEl) {
      let lazyEl = slideEl.querySelector(`.${spswiper.params.lazyPreloaderClass}`);
      if (!lazyEl && spswiper.isElement) {
        if (slideEl.shadowRoot) {
          lazyEl = slideEl.shadowRoot.querySelector(`.${spswiper.params.lazyPreloaderClass}`);
        } else {
          // init later
          requestAnimationFrame(() => {
            if (slideEl.shadowRoot) {
              lazyEl = slideEl.shadowRoot.querySelector(`.${spswiper.params.lazyPreloaderClass}`);
              if (lazyEl) lazyEl.remove();
            }
          });
        }
      }
      if (lazyEl) lazyEl.remove();
    }
  };
  const unlazy = (spswiper, index) => {
    if (!spswiper.slides[index]) return;
    const imageEl = spswiper.slides[index].querySelector('[loading="lazy"]');
    if (imageEl) imageEl.removeAttribute('loading');
  };
  const preload = spswiper => {
    if (!spswiper || spswiper.destroyed || !spswiper.params) return;
    let amount = spswiper.params.lazyPreloadPrevNext;
    const len = spswiper.slides.length;
    if (!len || !amount || amount < 0) return;
    amount = Math.min(amount, len);
    const slidesPerView = spswiper.params.slidesPerView === 'auto' ? spswiper.slidesPerViewDynamic() : Math.ceil(spswiper.params.slidesPerView);
    const activeIndex = spswiper.activeIndex;
    if (spswiper.params.grid && spswiper.params.grid.rows > 1) {
      const activeColumn = activeIndex;
      const preloadColumns = [activeColumn - amount];
      preloadColumns.push(...Array.from({
        length: amount
      }).map((_, i) => {
        return activeColumn + slidesPerView + i;
      }));
      spswiper.slides.forEach((slideEl, i) => {
        if (preloadColumns.includes(slideEl.column)) unlazy(spswiper, i);
      });
      return;
    }
    const slideIndexLastInView = activeIndex + slidesPerView - 1;
    if (spswiper.params.rewind || spswiper.params.loop) {
      for (let i = activeIndex - amount; i <= slideIndexLastInView + amount; i += 1) {
        const realIndex = (i % len + len) % len;
        if (realIndex < activeIndex || realIndex > slideIndexLastInView) unlazy(spswiper, realIndex);
      }
    } else {
      for (let i = Math.max(activeIndex - amount, 0); i <= Math.min(slideIndexLastInView + amount, len - 1); i += 1) {
        if (i !== activeIndex && (i > slideIndexLastInView || i < activeIndex)) {
          unlazy(spswiper, i);
        }
      }
    }
  };

  function getActiveIndexByTranslate(spswiper) {
    const {
      slidesGrid,
      params
    } = spswiper;
    const translate = spswiper.rtlTranslate ? spswiper.translate : -spswiper.translate;
    let activeIndex;
    for (let i = 0; i < slidesGrid.length; i += 1) {
      if (typeof slidesGrid[i + 1] !== 'undefined') {
        if (translate >= slidesGrid[i] && translate < slidesGrid[i + 1] - (slidesGrid[i + 1] - slidesGrid[i]) / 2) {
          activeIndex = i;
        } else if (translate >= slidesGrid[i] && translate < slidesGrid[i + 1]) {
          activeIndex = i + 1;
        }
      } else if (translate >= slidesGrid[i]) {
        activeIndex = i;
      }
    }
    // Normalize slideIndex
    if (params.normalizeSlideIndex) {
      if (activeIndex < 0 || typeof activeIndex === 'undefined') activeIndex = 0;
    }
    return activeIndex;
  }
  function updateActiveIndex(newActiveIndex) {
    const spswiper = this;
    const translate = spswiper.rtlTranslate ? spswiper.translate : -spswiper.translate;
    const {
      snapGrid,
      params,
      activeIndex: previousIndex,
      realIndex: previousRealIndex,
      snapIndex: previousSnapIndex
    } = spswiper;
    let activeIndex = newActiveIndex;
    let snapIndex;
    const getVirtualRealIndex = aIndex => {
      let realIndex = aIndex - spswiper.virtual.slidesBefore;
      if (realIndex < 0) {
        realIndex = spswiper.virtual.slides.length + realIndex;
      }
      if (realIndex >= spswiper.virtual.slides.length) {
        realIndex -= spswiper.virtual.slides.length;
      }
      return realIndex;
    };
    if (typeof activeIndex === 'undefined') {
      activeIndex = getActiveIndexByTranslate(spswiper);
    }
    if (snapGrid.indexOf(translate) >= 0) {
      snapIndex = snapGrid.indexOf(translate);
    } else {
      const skip = Math.min(params.slidesPerGroupSkip, activeIndex);
      snapIndex = skip + Math.floor((activeIndex - skip) / params.slidesPerGroup);
    }
    if (snapIndex >= snapGrid.length) snapIndex = snapGrid.length - 1;
    if (activeIndex === previousIndex) {
      if (snapIndex !== previousSnapIndex) {
        spswiper.snapIndex = snapIndex;
        spswiper.emit('snapIndexChange');
      }
      if (spswiper.params.loop && spswiper.virtual && spswiper.params.virtual.enabled) {
        spswiper.realIndex = getVirtualRealIndex(activeIndex);
      }
      return;
    }
    // Get real index
    let realIndex;
    if (spswiper.virtual && params.virtual.enabled && params.loop) {
      realIndex = getVirtualRealIndex(activeIndex);
    } else if (spswiper.slides[activeIndex]) {
      realIndex = parseInt(spswiper.slides[activeIndex].getAttribute('data-spswiper-slide-index') || activeIndex, 10);
    } else {
      realIndex = activeIndex;
    }
    Object.assign(spswiper, {
      previousSnapIndex,
      snapIndex,
      previousRealIndex,
      realIndex,
      previousIndex,
      activeIndex
    });
    if (spswiper.initialized) {
      preload(spswiper);
    }
    spswiper.emit('activeIndexChange');
    spswiper.emit('snapIndexChange');
    if (spswiper.initialized || spswiper.params.runCallbacksOnInit) {
      if (previousRealIndex !== realIndex) {
        spswiper.emit('realIndexChange');
      }
      spswiper.emit('slideChange');
    }
  }

  function updateClickedSlide(el, path) {
    const spswiper = this;
    const params = spswiper.params;
    let slide = el.closest(`.${params.slideClass}, spswiper-slide`);
    if (!slide && spswiper.isElement && path && path.length > 1 && path.includes(el)) {
      [...path.slice(path.indexOf(el) + 1, path.length)].forEach(pathEl => {
        if (!slide && pathEl.matches && pathEl.matches(`.${params.slideClass}, spswiper-slide`)) {
          slide = pathEl;
        }
      });
    }
    let slideFound = false;
    let slideIndex;
    if (slide) {
      for (let i = 0; i < spswiper.slides.length; i += 1) {
        if (spswiper.slides[i] === slide) {
          slideFound = true;
          slideIndex = i;
          break;
        }
      }
    }
    if (slide && slideFound) {
      spswiper.clickedSlide = slide;
      if (spswiper.virtual && spswiper.params.virtual.enabled) {
        spswiper.clickedIndex = parseInt(slide.getAttribute('data-spswiper-slide-index'), 10);
      } else {
        spswiper.clickedIndex = slideIndex;
      }
    } else {
      spswiper.clickedSlide = undefined;
      spswiper.clickedIndex = undefined;
      return;
    }
    if (params.slideToClickedSlide && spswiper.clickedIndex !== undefined && spswiper.clickedIndex !== spswiper.activeIndex) {
      spswiper.slideToClickedSlide();
    }
  }

  var update = {
    updateSize,
    updateSlides,
    updateAutoHeight,
    updateSlidesOffset,
    updateSlidesProgress,
    updateProgress,
    updateSlidesClasses,
    updateActiveIndex,
    updateClickedSlide
  };

  function getSPSwiperTranslate(axis) {
    if (axis === void 0) {
      axis = this.isHorizontal() ? 'x' : 'y';
    }
    const spswiper = this;
    const {
      params,
      rtlTranslate: rtl,
      translate,
      wrapperEl
    } = spswiper;
    if (params.virtualTranslate) {
      return rtl ? -translate : translate;
    }
    if (params.cssMode) {
      return translate;
    }
    let currentTranslate = getTranslate(wrapperEl, axis);
    currentTranslate += spswiper.cssOverflowAdjustment();
    if (rtl) currentTranslate = -currentTranslate;
    return currentTranslate || 0;
  }

  function setTranslate(translate, byController) {
    const spswiper = this;
    const {
      rtlTranslate: rtl,
      params,
      wrapperEl,
      progress
    } = spswiper;
    let x = 0;
    let y = 0;
    const z = 0;
    if (spswiper.isHorizontal()) {
      x = rtl ? -translate : translate;
    } else {
      y = translate;
    }
    if (params.roundLengths) {
      x = Math.floor(x);
      y = Math.floor(y);
    }
    spswiper.previousTranslate = spswiper.translate;
    spswiper.translate = spswiper.isHorizontal() ? x : y;
    if (params.cssMode) {
      wrapperEl[spswiper.isHorizontal() ? 'scrollLeft' : 'scrollTop'] = spswiper.isHorizontal() ? -x : -y;
    } else if (!params.virtualTranslate) {
      if (spswiper.isHorizontal()) {
        x -= spswiper.cssOverflowAdjustment();
      } else {
        y -= spswiper.cssOverflowAdjustment();
      }
      wrapperEl.style.transform = `translate3d(${x}px, ${y}px, ${z}px)`;
    }

    // Check if we need to update progress
    let newProgress;
    const translatesDiff = spswiper.maxTranslate() - spswiper.minTranslate();
    if (translatesDiff === 0) {
      newProgress = 0;
    } else {
      newProgress = (translate - spswiper.minTranslate()) / translatesDiff;
    }
    if (newProgress !== progress) {
      spswiper.updateProgress(translate);
    }
    spswiper.emit('setTranslate', spswiper.translate, byController);
  }

  function minTranslate() {
    return -this.snapGrid[0];
  }

  function maxTranslate() {
    return -this.snapGrid[this.snapGrid.length - 1];
  }

  function translateTo(translate, speed, runCallbacks, translateBounds, internal) {
    if (translate === void 0) {
      translate = 0;
    }
    if (speed === void 0) {
      speed = this.params.speed;
    }
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    if (translateBounds === void 0) {
      translateBounds = true;
    }
    const spswiper = this;
    const {
      params,
      wrapperEl
    } = spswiper;
    if (spswiper.animating && params.preventInteractionOnTransition) {
      return false;
    }
    const minTranslate = spswiper.minTranslate();
    const maxTranslate = spswiper.maxTranslate();
    let newTranslate;
    if (translateBounds && translate > minTranslate) newTranslate = minTranslate;else if (translateBounds && translate < maxTranslate) newTranslate = maxTranslate;else newTranslate = translate;

    // Update progress
    spswiper.updateProgress(newTranslate);
    if (params.cssMode) {
      const isH = spswiper.isHorizontal();
      if (speed === 0) {
        wrapperEl[isH ? 'scrollLeft' : 'scrollTop'] = -newTranslate;
      } else {
        if (!spswiper.support.smoothScroll) {
          animateCSSModeScroll({
            spswiper,
            targetPosition: -newTranslate,
            side: isH ? 'left' : 'top'
          });
          return true;
        }
        wrapperEl.scrollTo({
          [isH ? 'left' : 'top']: -newTranslate,
          behavior: 'smooth'
        });
      }
      return true;
    }
    if (speed === 0) {
      spswiper.setTransition(0);
      spswiper.setTranslate(newTranslate);
      if (runCallbacks) {
        spswiper.emit('beforeTransitionStart', speed, internal);
        spswiper.emit('transitionEnd');
      }
    } else {
      spswiper.setTransition(speed);
      spswiper.setTranslate(newTranslate);
      if (runCallbacks) {
        spswiper.emit('beforeTransitionStart', speed, internal);
        spswiper.emit('transitionStart');
      }
      if (!spswiper.animating) {
        spswiper.animating = true;
        if (!spswiper.onTranslateToWrapperTransitionEnd) {
          spswiper.onTranslateToWrapperTransitionEnd = function transitionEnd(e) {
            if (!spswiper || spswiper.destroyed) return;
            if (e.target !== this) return;
            spswiper.wrapperEl.removeEventListener('transitionend', spswiper.onTranslateToWrapperTransitionEnd);
            spswiper.onTranslateToWrapperTransitionEnd = null;
            delete spswiper.onTranslateToWrapperTransitionEnd;
            if (runCallbacks) {
              spswiper.emit('transitionEnd');
            }
          };
        }
        spswiper.wrapperEl.addEventListener('transitionend', spswiper.onTranslateToWrapperTransitionEnd);
      }
    }
    return true;
  }

  var translate = {
    getTranslate: getSPSwiperTranslate,
    setTranslate,
    minTranslate,
    maxTranslate,
    translateTo
  };

  function setTransition(duration, byController) {
    const spswiper = this;
    if (!spswiper.params.cssMode) {
      spswiper.wrapperEl.style.transitionDuration = `${duration}ms`;
      spswiper.wrapperEl.style.transitionDelay = duration === 0 ? `0ms` : '';
    }
    spswiper.emit('setTransition', duration, byController);
  }

  function transitionEmit(_ref) {
    let {
      spswiper,
      runCallbacks,
      direction,
      step
    } = _ref;
    const {
      activeIndex,
      previousIndex
    } = spswiper;
    let dir = direction;
    if (!dir) {
      if (activeIndex > previousIndex) dir = 'next';else if (activeIndex < previousIndex) dir = 'prev';else dir = 'reset';
    }
    spswiper.emit(`transition${step}`);
    if (runCallbacks && activeIndex !== previousIndex) {
      if (dir === 'reset') {
        spswiper.emit(`slideResetTransition${step}`);
        return;
      }
      spswiper.emit(`slideChangeTransition${step}`);
      if (dir === 'next') {
        spswiper.emit(`slideNextTransition${step}`);
      } else {
        spswiper.emit(`slidePrevTransition${step}`);
      }
    }
  }

  function transitionStart(runCallbacks, direction) {
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    const spswiper = this;
    const {
      params
    } = spswiper;
    if (params.cssMode) return;
    if (params.autoHeight) {
      spswiper.updateAutoHeight();
    }
    transitionEmit({
      spswiper,
      runCallbacks,
      direction,
      step: 'Start'
    });
  }

  function transitionEnd(runCallbacks, direction) {
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    const spswiper = this;
    const {
      params
    } = spswiper;
    spswiper.animating = false;
    if (params.cssMode) return;
    spswiper.setTransition(0);
    transitionEmit({
      spswiper,
      runCallbacks,
      direction,
      step: 'End'
    });
  }

  var transition = {
    setTransition,
    transitionStart,
    transitionEnd
  };

  function slideTo(index, speed, runCallbacks, internal, initial) {
    if (index === void 0) {
      index = 0;
    }
    if (speed === void 0) {
      speed = this.params.speed;
    }
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    if (typeof index === 'string') {
      index = parseInt(index, 10);
    }
    const spswiper = this;
    let slideIndex = index;
    if (slideIndex < 0) slideIndex = 0;
    const {
      params,
      snapGrid,
      slidesGrid,
      previousIndex,
      activeIndex,
      rtlTranslate: rtl,
      wrapperEl,
      enabled
    } = spswiper;
    if (spswiper.animating && params.preventInteractionOnTransition || !enabled && !internal && !initial) {
      return false;
    }
    const skip = Math.min(spswiper.params.slidesPerGroupSkip, slideIndex);
    let snapIndex = skip + Math.floor((slideIndex - skip) / spswiper.params.slidesPerGroup);
    if (snapIndex >= snapGrid.length) snapIndex = snapGrid.length - 1;
    const translate = -snapGrid[snapIndex];
    // Normalize slideIndex
    if (params.normalizeSlideIndex) {
      for (let i = 0; i < slidesGrid.length; i += 1) {
        const normalizedTranslate = -Math.floor(translate * 100);
        const normalizedGrid = Math.floor(slidesGrid[i] * 100);
        const normalizedGridNext = Math.floor(slidesGrid[i + 1] * 100);
        if (typeof slidesGrid[i + 1] !== 'undefined') {
          if (normalizedTranslate >= normalizedGrid && normalizedTranslate < normalizedGridNext - (normalizedGridNext - normalizedGrid) / 2) {
            slideIndex = i;
          } else if (normalizedTranslate >= normalizedGrid && normalizedTranslate < normalizedGridNext) {
            slideIndex = i + 1;
          }
        } else if (normalizedTranslate >= normalizedGrid) {
          slideIndex = i;
        }
      }
    }
    // Directions locks
    if (spswiper.initialized && slideIndex !== activeIndex) {
      if (!spswiper.allowSlideNext && (rtl ? translate > spswiper.translate && translate > spswiper.minTranslate() : translate < spswiper.translate && translate < spswiper.minTranslate())) {
        return false;
      }
      if (!spswiper.allowSlidePrev && translate > spswiper.translate && translate > spswiper.maxTranslate()) {
        if ((activeIndex || 0) !== slideIndex) {
          return false;
        }
      }
    }
    if (slideIndex !== (previousIndex || 0) && runCallbacks) {
      spswiper.emit('beforeSlideChangeStart');
    }

    // Update progress
    spswiper.updateProgress(translate);
    let direction;
    if (slideIndex > activeIndex) direction = 'next';else if (slideIndex < activeIndex) direction = 'prev';else direction = 'reset';

    // Update Index
    if (rtl && -translate === spswiper.translate || !rtl && translate === spswiper.translate) {
      spswiper.updateActiveIndex(slideIndex);
      // Update Height
      if (params.autoHeight) {
        spswiper.updateAutoHeight();
      }
      spswiper.updateSlidesClasses();
      if (params.effect !== 'slide') {
        spswiper.setTranslate(translate);
      }
      if (direction !== 'reset') {
        spswiper.transitionStart(runCallbacks, direction);
        spswiper.transitionEnd(runCallbacks, direction);
      }
      return false;
    }
    if (params.cssMode) {
      const isH = spswiper.isHorizontal();
      const t = rtl ? translate : -translate;
      if (speed === 0) {
        const isVirtual = spswiper.virtual && spswiper.params.virtual.enabled;
        if (isVirtual) {
          spswiper.wrapperEl.style.scrollSnapType = 'none';
          spswiper._immediateVirtual = true;
        }
        if (isVirtual && !spswiper._cssModeVirtualInitialSet && spswiper.params.initialSlide > 0) {
          spswiper._cssModeVirtualInitialSet = true;
          requestAnimationFrame(() => {
            wrapperEl[isH ? 'scrollLeft' : 'scrollTop'] = t;
          });
        } else {
          wrapperEl[isH ? 'scrollLeft' : 'scrollTop'] = t;
        }
        if (isVirtual) {
          requestAnimationFrame(() => {
            spswiper.wrapperEl.style.scrollSnapType = '';
            spswiper._immediateVirtual = false;
          });
        }
      } else {
        if (!spswiper.support.smoothScroll) {
          animateCSSModeScroll({
            spswiper,
            targetPosition: t,
            side: isH ? 'left' : 'top'
          });
          return true;
        }
        wrapperEl.scrollTo({
          [isH ? 'left' : 'top']: t,
          behavior: 'smooth'
        });
      }
      return true;
    }
    spswiper.setTransition(speed);
    spswiper.setTranslate(translate);
    spswiper.updateActiveIndex(slideIndex);
    spswiper.updateSlidesClasses();
    spswiper.emit('beforeTransitionStart', speed, internal);
    spswiper.transitionStart(runCallbacks, direction);
    if (speed === 0) {
      spswiper.transitionEnd(runCallbacks, direction);
    } else if (!spswiper.animating) {
      spswiper.animating = true;
      if (!spswiper.onSlideToWrapperTransitionEnd) {
        spswiper.onSlideToWrapperTransitionEnd = function transitionEnd(e) {
          if (!spswiper || spswiper.destroyed) return;
          if (e.target !== this) return;
          spswiper.wrapperEl.removeEventListener('transitionend', spswiper.onSlideToWrapperTransitionEnd);
          spswiper.onSlideToWrapperTransitionEnd = null;
          delete spswiper.onSlideToWrapperTransitionEnd;
          spswiper.transitionEnd(runCallbacks, direction);
        };
      }
      spswiper.wrapperEl.addEventListener('transitionend', spswiper.onSlideToWrapperTransitionEnd);
    }
    return true;
  }

  function slideToLoop(index, speed, runCallbacks, internal) {
    if (index === void 0) {
      index = 0;
    }
    if (speed === void 0) {
      speed = this.params.speed;
    }
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    if (typeof index === 'string') {
      const indexAsNumber = parseInt(index, 10);
      index = indexAsNumber;
    }
    const spswiper = this;
    let newIndex = index;
    if (spswiper.params.loop) {
      if (spswiper.virtual && spswiper.params.virtual.enabled) {
        // eslint-disable-next-line
        newIndex = newIndex + spswiper.virtual.slidesBefore;
      } else {
        newIndex = spswiper.getSlideIndexByData(newIndex);
      }
    }
    return spswiper.slideTo(newIndex, speed, runCallbacks, internal);
  }

  /* eslint no-unused-vars: "off" */
  function slideNext(speed, runCallbacks, internal) {
    if (speed === void 0) {
      speed = this.params.speed;
    }
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    const spswiper = this;
    const {
      enabled,
      params,
      animating
    } = spswiper;
    if (!enabled) return spswiper;
    let perGroup = params.slidesPerGroup;
    if (params.slidesPerView === 'auto' && params.slidesPerGroup === 1 && params.slidesPerGroupAuto) {
      perGroup = Math.max(spswiper.slidesPerViewDynamic('current', true), 1);
    }
    const increment = spswiper.activeIndex < params.slidesPerGroupSkip ? 1 : perGroup;
    const isVirtual = spswiper.virtual && params.virtual.enabled;
    if (params.loop) {
      if (animating && !isVirtual && params.loopPreventsSliding) return false;
      spswiper.loopFix({
        direction: 'next'
      });
      // eslint-disable-next-line
      spswiper._clientLeft = spswiper.wrapperEl.clientLeft;
      if (spswiper.activeIndex === spswiper.slides.length - 1 && params.cssMode) {
        requestAnimationFrame(() => {
          spswiper.slideTo(spswiper.activeIndex + increment, speed, runCallbacks, internal);
        });
        return true;
      }
    }
    if (params.rewind && spswiper.isEnd) {
      return spswiper.slideTo(0, speed, runCallbacks, internal);
    }
    return spswiper.slideTo(spswiper.activeIndex + increment, speed, runCallbacks, internal);
  }

  /* eslint no-unused-vars: "off" */
  function slidePrev(speed, runCallbacks, internal) {
    if (speed === void 0) {
      speed = this.params.speed;
    }
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    const spswiper = this;
    const {
      params,
      snapGrid,
      slidesGrid,
      rtlTranslate,
      enabled,
      animating
    } = spswiper;
    if (!enabled) return spswiper;
    const isVirtual = spswiper.virtual && params.virtual.enabled;
    if (params.loop) {
      if (animating && !isVirtual && params.loopPreventsSliding) return false;
      spswiper.loopFix({
        direction: 'prev'
      });
      // eslint-disable-next-line
      spswiper._clientLeft = spswiper.wrapperEl.clientLeft;
    }
    const translate = rtlTranslate ? spswiper.translate : -spswiper.translate;
    function normalize(val) {
      if (val < 0) return -Math.floor(Math.abs(val));
      return Math.floor(val);
    }
    const normalizedTranslate = normalize(translate);
    const normalizedSnapGrid = snapGrid.map(val => normalize(val));
    let prevSnap = snapGrid[normalizedSnapGrid.indexOf(normalizedTranslate) - 1];
    if (typeof prevSnap === 'undefined' && params.cssMode) {
      let prevSnapIndex;
      snapGrid.forEach((snap, snapIndex) => {
        if (normalizedTranslate >= snap) {
          // prevSnap = snap;
          prevSnapIndex = snapIndex;
        }
      });
      if (typeof prevSnapIndex !== 'undefined') {
        prevSnap = snapGrid[prevSnapIndex > 0 ? prevSnapIndex - 1 : prevSnapIndex];
      }
    }
    let prevIndex = 0;
    if (typeof prevSnap !== 'undefined') {
      prevIndex = slidesGrid.indexOf(prevSnap);
      if (prevIndex < 0) prevIndex = spswiper.activeIndex - 1;
      if (params.slidesPerView === 'auto' && params.slidesPerGroup === 1 && params.slidesPerGroupAuto) {
        prevIndex = prevIndex - spswiper.slidesPerViewDynamic('previous', true) + 1;
        prevIndex = Math.max(prevIndex, 0);
      }
    }
    if (params.rewind && spswiper.isBeginning) {
      const lastIndex = spswiper.params.virtual && spswiper.params.virtual.enabled && spswiper.virtual ? spswiper.virtual.slides.length - 1 : spswiper.slides.length - 1;
      return spswiper.slideTo(lastIndex, speed, runCallbacks, internal);
    } else if (params.loop && spswiper.activeIndex === 0 && params.cssMode) {
      requestAnimationFrame(() => {
        spswiper.slideTo(prevIndex, speed, runCallbacks, internal);
      });
      return true;
    }
    return spswiper.slideTo(prevIndex, speed, runCallbacks, internal);
  }

  /* eslint no-unused-vars: "off" */
  function slideReset(speed, runCallbacks, internal) {
    if (speed === void 0) {
      speed = this.params.speed;
    }
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    const spswiper = this;
    return spswiper.slideTo(spswiper.activeIndex, speed, runCallbacks, internal);
  }

  /* eslint no-unused-vars: "off" */
  function slideToClosest(speed, runCallbacks, internal, threshold) {
    if (speed === void 0) {
      speed = this.params.speed;
    }
    if (runCallbacks === void 0) {
      runCallbacks = true;
    }
    if (threshold === void 0) {
      threshold = 0.5;
    }
    const spswiper = this;
    let index = spswiper.activeIndex;
    const skip = Math.min(spswiper.params.slidesPerGroupSkip, index);
    const snapIndex = skip + Math.floor((index - skip) / spswiper.params.slidesPerGroup);
    const translate = spswiper.rtlTranslate ? spswiper.translate : -spswiper.translate;
    if (translate >= spswiper.snapGrid[snapIndex]) {
      // The current translate is on or after the current snap index, so the choice
      // is between the current index and the one after it.
      const currentSnap = spswiper.snapGrid[snapIndex];
      const nextSnap = spswiper.snapGrid[snapIndex + 1];
      if (translate - currentSnap > (nextSnap - currentSnap) * threshold) {
        index += spswiper.params.slidesPerGroup;
      }
    } else {
      // The current translate is before the current snap index, so the choice
      // is between the current index and the one before it.
      const prevSnap = spswiper.snapGrid[snapIndex - 1];
      const currentSnap = spswiper.snapGrid[snapIndex];
      if (translate - prevSnap <= (currentSnap - prevSnap) * threshold) {
        index -= spswiper.params.slidesPerGroup;
      }
    }
    index = Math.max(index, 0);
    index = Math.min(index, spswiper.slidesGrid.length - 1);
    return spswiper.slideTo(index, speed, runCallbacks, internal);
  }

  function slideToClickedSlide() {
    const spswiper = this;
    const {
      params,
      slidesEl
    } = spswiper;
    const slidesPerView = params.slidesPerView === 'auto' ? spswiper.slidesPerViewDynamic() : params.slidesPerView;
    let slideToIndex = spswiper.clickedIndex;
    let realIndex;
    const slideSelector = spswiper.isElement ? `spswiper-slide` : `.${params.slideClass}`;
    if (params.loop) {
      if (spswiper.animating) return;
      realIndex = parseInt(spswiper.clickedSlide.getAttribute('data-spswiper-slide-index'), 10);
      if (params.centeredSlides) {
        if (slideToIndex < spswiper.loopedSlides - slidesPerView / 2 || slideToIndex > spswiper.slides.length - spswiper.loopedSlides + slidesPerView / 2) {
          spswiper.loopFix();
          slideToIndex = spswiper.getSlideIndex(elementChildren(slidesEl, `${slideSelector}[data-spswiper-slide-index="${realIndex}"]`)[0]);
          nextTick(() => {
            spswiper.slideTo(slideToIndex);
          });
        } else {
          spswiper.slideTo(slideToIndex);
        }
      } else if (slideToIndex > spswiper.slides.length - slidesPerView) {
        spswiper.loopFix();
        slideToIndex = spswiper.getSlideIndex(elementChildren(slidesEl, `${slideSelector}[data-spswiper-slide-index="${realIndex}"]`)[0]);
        nextTick(() => {
          spswiper.slideTo(slideToIndex);
        });
      } else {
        spswiper.slideTo(slideToIndex);
      }
    } else {
      spswiper.slideTo(slideToIndex);
    }
  }

  var slide = {
    slideTo,
    slideToLoop,
    slideNext,
    slidePrev,
    slideReset,
    slideToClosest,
    slideToClickedSlide
  };

  function loopCreate(slideRealIndex) {
    const spswiper = this;
    const {
      params,
      slidesEl
    } = spswiper;
    if (!params.loop || spswiper.virtual && spswiper.params.virtual.enabled) return;
    const slides = elementChildren(slidesEl, `.${params.slideClass}, spswiper-slide`);
    slides.forEach((el, index) => {
      el.setAttribute('data-spswiper-slide-index', index);
    });
    spswiper.loopFix({
      slideRealIndex,
      direction: params.centeredSlides ? undefined : 'next'
    });
  }

  function loopFix(_temp) {
    let {
      slideRealIndex,
      slideTo = true,
      direction,
      setTranslate,
      activeSlideIndex,
      byController,
      byMousewheel
    } = _temp === void 0 ? {} : _temp;
    const spswiper = this;
    if (!spswiper.params.loop) return;
    spswiper.emit('beforeLoopFix');
    const {
      slides,
      allowSlidePrev,
      allowSlideNext,
      slidesEl,
      params
    } = spswiper;
    spswiper.allowSlidePrev = true;
    spswiper.allowSlideNext = true;
    if (spswiper.virtual && params.virtual.enabled) {
      if (slideTo) {
        if (!params.centeredSlides && spswiper.snapIndex === 0) {
          spswiper.slideTo(spswiper.virtual.slides.length, 0, false, true);
        } else if (params.centeredSlides && spswiper.snapIndex < params.slidesPerView) {
          spswiper.slideTo(spswiper.virtual.slides.length + spswiper.snapIndex, 0, false, true);
        } else if (spswiper.snapIndex === spswiper.snapGrid.length - 1) {
          spswiper.slideTo(spswiper.virtual.slidesBefore, 0, false, true);
        }
      }
      spswiper.allowSlidePrev = allowSlidePrev;
      spswiper.allowSlideNext = allowSlideNext;
      spswiper.emit('loopFix');
      return;
    }
    const slidesPerView = params.slidesPerView === 'auto' ? spswiper.slidesPerViewDynamic() : Math.ceil(parseFloat(params.slidesPerView, 10));
    let loopedSlides = params.loopedSlides || slidesPerView;
    if (loopedSlides % params.slidesPerGroup !== 0) {
      loopedSlides += params.slidesPerGroup - loopedSlides % params.slidesPerGroup;
    }
    spswiper.loopedSlides = loopedSlides;
    const prependSlidesIndexes = [];
    const appendSlidesIndexes = [];
    let activeIndex = spswiper.activeIndex;
    if (typeof activeSlideIndex === 'undefined') {
      activeSlideIndex = spswiper.getSlideIndex(spswiper.slides.filter(el => el.classList.contains(params.slideActiveClass))[0]);
    } else {
      activeIndex = activeSlideIndex;
    }
    const isNext = direction === 'next' || !direction;
    const isPrev = direction === 'prev' || !direction;
    let slidesPrepended = 0;
    let slidesAppended = 0;
    // prepend last slides before start
    if (activeSlideIndex < loopedSlides) {
      slidesPrepended = Math.max(loopedSlides - activeSlideIndex, params.slidesPerGroup);
      for (let i = 0; i < loopedSlides - activeSlideIndex; i += 1) {
        const index = i - Math.floor(i / slides.length) * slides.length;
        prependSlidesIndexes.push(slides.length - index - 1);
      }
    } else if (activeSlideIndex /* + slidesPerView */ > spswiper.slides.length - loopedSlides * 2) {
      slidesAppended = Math.max(activeSlideIndex - (spswiper.slides.length - loopedSlides * 2), params.slidesPerGroup);
      for (let i = 0; i < slidesAppended; i += 1) {
        const index = i - Math.floor(i / slides.length) * slides.length;
        appendSlidesIndexes.push(index);
      }
    }
    if (isPrev) {
      prependSlidesIndexes.forEach(index => {
        spswiper.slides[index].spswiperLoopMoveDOM = true;
        slidesEl.prepend(spswiper.slides[index]);
        spswiper.slides[index].spswiperLoopMoveDOM = false;
      });
    }
    if (isNext) {
      appendSlidesIndexes.forEach(index => {
        spswiper.slides[index].spswiperLoopMoveDOM = true;
        slidesEl.append(spswiper.slides[index]);
        spswiper.slides[index].spswiperLoopMoveDOM = false;
      });
    }
    spswiper.recalcSlides();
    if (params.slidesPerView === 'auto') {
      spswiper.updateSlides();
    }
    if (params.watchSlidesProgress) {
      spswiper.updateSlidesOffset();
    }
    if (slideTo) {
      if (prependSlidesIndexes.length > 0 && isPrev) {
        if (typeof slideRealIndex === 'undefined') {
          const currentSlideTranslate = spswiper.slidesGrid[activeIndex];
          const newSlideTranslate = spswiper.slidesGrid[activeIndex + slidesPrepended];
          const diff = newSlideTranslate - currentSlideTranslate;
          if (byMousewheel) {
            spswiper.setTranslate(spswiper.translate - diff);
          } else {
            spswiper.slideTo(activeIndex + slidesPrepended, 0, false, true);
            if (setTranslate) {
              spswiper.touches[spswiper.isHorizontal() ? 'startX' : 'startY'] += diff;
              spswiper.touchEventsData.currentTranslate = spswiper.translate;
            }
          }
        } else {
          if (setTranslate) {
            spswiper.slideToLoop(slideRealIndex, 0, false, true);
            spswiper.touchEventsData.currentTranslate = spswiper.translate;
          }
        }
      } else if (appendSlidesIndexes.length > 0 && isNext) {
        if (typeof slideRealIndex === 'undefined') {
          const currentSlideTranslate = spswiper.slidesGrid[activeIndex];
          const newSlideTranslate = spswiper.slidesGrid[activeIndex - slidesAppended];
          const diff = newSlideTranslate - currentSlideTranslate;
          if (byMousewheel) {
            spswiper.setTranslate(spswiper.translate - diff);
          } else {
            spswiper.slideTo(activeIndex - slidesAppended, 0, false, true);
            if (setTranslate) {
              spswiper.touches[spswiper.isHorizontal() ? 'startX' : 'startY'] += diff;
              spswiper.touchEventsData.currentTranslate = spswiper.translate;
            }
          }
        } else {
          spswiper.slideToLoop(slideRealIndex, 0, false, true);
        }
      }
    }
    spswiper.allowSlidePrev = allowSlidePrev;
    spswiper.allowSlideNext = allowSlideNext;
    if (spswiper.controller && spswiper.controller.control && !byController) {
      const loopParams = {
        slideRealIndex,
        direction,
        setTranslate,
        activeSlideIndex,
        byController: true
      };
      if (Array.isArray(spswiper.controller.control)) {
        spswiper.controller.control.forEach(c => {
          if (!c.destroyed && c.params.loop) c.loopFix({
            ...loopParams,
            slideTo: c.params.slidesPerView === params.slidesPerView ? slideTo : false
          });
        });
      } else if (spswiper.controller.control instanceof spswiper.constructor && spswiper.controller.control.params.loop) {
        spswiper.controller.control.loopFix({
          ...loopParams,
          slideTo: spswiper.controller.control.params.slidesPerView === params.slidesPerView ? slideTo : false
        });
      }
    }
    spswiper.emit('loopFix');
  }

  function loopDestroy() {
    const spswiper = this;
    const {
      params,
      slidesEl
    } = spswiper;
    if (!params.loop || spswiper.virtual && spswiper.params.virtual.enabled) return;
    spswiper.recalcSlides();
    const newSlidesOrder = [];
    spswiper.slides.forEach(slideEl => {
      const index = typeof slideEl.spswiperSlideIndex === 'undefined' ? slideEl.getAttribute('data-spswiper-slide-index') * 1 : slideEl.spswiperSlideIndex;
      newSlidesOrder[index] = slideEl;
    });
    spswiper.slides.forEach(slideEl => {
      slideEl.removeAttribute('data-spswiper-slide-index');
    });
    newSlidesOrder.forEach(slideEl => {
      slidesEl.append(slideEl);
    });
    spswiper.recalcSlides();
    spswiper.slideTo(spswiper.realIndex, 0);
  }

  var loop = {
    loopCreate,
    loopFix,
    loopDestroy
  };

  function setGrabCursor(moving) {
    const spswiper = this;
    if (!spswiper.params.simulateTouch || spswiper.params.watchOverflow && spswiper.isLocked || spswiper.params.cssMode) return;
    const el = spswiper.params.touchEventsTarget === 'container' ? spswiper.el : spswiper.wrapperEl;
    if (spswiper.isElement) {
      spswiper.__preventObserver__ = true;
    }
    el.style.cursor = 'move';
    el.style.cursor = moving ? 'grabbing' : 'grab';
    if (spswiper.isElement) {
      requestAnimationFrame(() => {
        spswiper.__preventObserver__ = false;
      });
    }
  }

  function unsetGrabCursor() {
    const spswiper = this;
    if (spswiper.params.watchOverflow && spswiper.isLocked || spswiper.params.cssMode) {
      return;
    }
    if (spswiper.isElement) {
      spswiper.__preventObserver__ = true;
    }
    spswiper[spswiper.params.touchEventsTarget === 'container' ? 'el' : 'wrapperEl'].style.cursor = '';
    if (spswiper.isElement) {
      requestAnimationFrame(() => {
        spswiper.__preventObserver__ = false;
      });
    }
  }

  var grabCursor = {
    setGrabCursor,
    unsetGrabCursor
  };

  // Modified from https://stackoverflow.com/questions/54520554/custom-element-getrootnode-closest-function-crossing-multiple-parent-shadowd
  function closestElement(selector, base) {
    if (base === void 0) {
      base = this;
    }
    function __closestFrom(el) {
      if (!el || el === getDocument() || el === getWindow()) return null;
      if (el.assignedSlot) el = el.assignedSlot;
      const found = el.closest(selector);
      if (!found && !el.getRootNode) {
        return null;
      }
      return found || __closestFrom(el.getRootNode().host);
    }
    return __closestFrom(base);
  }
  function onTouchStart(event) {
    const spswiper = this;
    const document = getDocument();
    const window = getWindow();
    const data = spswiper.touchEventsData;
    data.evCache.push(event);
    const {
      params,
      touches,
      enabled
    } = spswiper;
    if (!enabled) return;
    if (!params.simulateTouch && event.pointerType === 'mouse') return;
    if (spswiper.animating && params.preventInteractionOnTransition) {
      return;
    }
    if (!spswiper.animating && params.cssMode && params.loop) {
      spswiper.loopFix();
    }
    let e = event;
    if (e.originalEvent) e = e.originalEvent;
    let targetEl = e.target;
    if (params.touchEventsTarget === 'wrapper') {
      if (!spswiper.wrapperEl.contains(targetEl)) return;
    }
    if ('which' in e && e.which === 3) return;
    if ('button' in e && e.button > 0) return;
    if (data.isTouched && data.isMoved) return;

    // change target el for shadow root component
    const swipingClassHasValue = !!params.noSwipingClass && params.noSwipingClass !== '';
    // eslint-disable-next-line
    const eventPath = event.composedPath ? event.composedPath() : event.path;
    if (swipingClassHasValue && e.target && e.target.shadowRoot && eventPath) {
      targetEl = eventPath[0];
    }
    const noSwipingSelector = params.noSwipingSelector ? params.noSwipingSelector : `.${params.noSwipingClass}`;
    const isTargetShadow = !!(e.target && e.target.shadowRoot);

    // use closestElement for shadow root element to get the actual closest for nested shadow root element
    if (params.noSwiping && (isTargetShadow ? closestElement(noSwipingSelector, targetEl) : targetEl.closest(noSwipingSelector))) {
      spswiper.allowClick = true;
      return;
    }
    if (params.swipeHandler) {
      if (!targetEl.closest(params.swipeHandler)) return;
    }
    touches.currentX = e.pageX;
    touches.currentY = e.pageY;
    const startX = touches.currentX;
    const startY = touches.currentY;

    // Do NOT start if iOS edge swipe is detected. Otherwise iOS app cannot swipe-to-go-back anymore

    const edgeSwipeDetection = params.edgeSwipeDetection || params.iOSEdgeSwipeDetection;
    const edgeSwipeThreshold = params.edgeSwipeThreshold || params.iOSEdgeSwipeThreshold;
    if (edgeSwipeDetection && (startX <= edgeSwipeThreshold || startX >= window.innerWidth - edgeSwipeThreshold)) {
      if (edgeSwipeDetection === 'prevent') {
        event.preventDefault();
      } else {
        return;
      }
    }
    Object.assign(data, {
      isTouched: true,
      isMoved: false,
      allowTouchCallbacks: true,
      isScrolling: undefined,
      startMoving: undefined
    });
    touches.startX = startX;
    touches.startY = startY;
    data.touchStartTime = now();
    spswiper.allowClick = true;
    spswiper.updateSize();
    spswiper.swipeDirection = undefined;
    if (params.threshold > 0) data.allowThresholdMove = false;
    let preventDefault = true;
    if (targetEl.matches(data.focusableElements)) {
      preventDefault = false;
      if (targetEl.nodeName === 'SELECT') {
        data.isTouched = false;
      }
    }
    if (document.activeElement && document.activeElement.matches(data.focusableElements) && document.activeElement !== targetEl) {
      document.activeElement.blur();
    }
    const shouldPreventDefault = preventDefault && spswiper.allowTouchMove && params.touchStartPreventDefault;
    if ((params.touchStartForcePreventDefault || shouldPreventDefault) && !targetEl.isContentEditable) {
      e.preventDefault();
    }
    if (params.freeMode && params.freeMode.enabled && spswiper.freeMode && spswiper.animating && !params.cssMode) {
      spswiper.freeMode.onTouchStart();
    }
    spswiper.emit('touchStart', e);
  }

  function onTouchMove(event) {
    const document = getDocument();
    const spswiper = this;
    const data = spswiper.touchEventsData;
    const {
      params,
      touches,
      rtlTranslate: rtl,
      enabled
    } = spswiper;
    if (!enabled) return;
    if (!params.simulateTouch && event.pointerType === 'mouse') return;
    let e = event;
    if (e.originalEvent) e = e.originalEvent;
    if (!data.isTouched) {
      if (data.startMoving && data.isScrolling) {
        spswiper.emit('touchMoveOpposite', e);
      }
      return;
    }
    const pointerIndex = data.evCache.findIndex(cachedEv => cachedEv.pointerId === e.pointerId);
    if (pointerIndex >= 0) data.evCache[pointerIndex] = e;
    const targetTouch = data.evCache.length > 1 ? data.evCache[0] : e;
    const pageX = targetTouch.pageX;
    const pageY = targetTouch.pageY;
    if (e.preventedByNestedSPSwiper) {
      touches.startX = pageX;
      touches.startY = pageY;
      return;
    }
    if (!spswiper.allowTouchMove) {
      if (!e.target.matches(data.focusableElements)) {
        spswiper.allowClick = false;
      }
      if (data.isTouched) {
        Object.assign(touches, {
          startX: pageX,
          startY: pageY,
          prevX: spswiper.touches.currentX,
          prevY: spswiper.touches.currentY,
          currentX: pageX,
          currentY: pageY
        });
        data.touchStartTime = now();
      }
      return;
    }
    if (params.touchReleaseOnEdges && !params.loop) {
      if (spswiper.isVertical()) {
        // Vertical
        if (pageY < touches.startY && spswiper.translate <= spswiper.maxTranslate() || pageY > touches.startY && spswiper.translate >= spswiper.minTranslate()) {
          data.isTouched = false;
          data.isMoved = false;
          return;
        }
      } else if (pageX < touches.startX && spswiper.translate <= spswiper.maxTranslate() || pageX > touches.startX && spswiper.translate >= spswiper.minTranslate()) {
        return;
      }
    }
    if (document.activeElement) {
      if (e.target === document.activeElement && e.target.matches(data.focusableElements)) {
        data.isMoved = true;
        spswiper.allowClick = false;
        return;
      }
    }
    if (data.allowTouchCallbacks) {
      spswiper.emit('touchMove', e);
    }
    if (e.targetTouches && e.targetTouches.length > 1) return;
    touches.currentX = pageX;
    touches.currentY = pageY;
    const diffX = touches.currentX - touches.startX;
    const diffY = touches.currentY - touches.startY;
    if (spswiper.params.threshold && Math.sqrt(diffX ** 2 + diffY ** 2) < spswiper.params.threshold) return;
    if (typeof data.isScrolling === 'undefined') {
      let touchAngle;
      if (spswiper.isHorizontal() && touches.currentY === touches.startY || spswiper.isVertical() && touches.currentX === touches.startX) {
        data.isScrolling = false;
      } else {
        // eslint-disable-next-line
        if (diffX * diffX + diffY * diffY >= 25) {
          touchAngle = Math.atan2(Math.abs(diffY), Math.abs(diffX)) * 180 / Math.PI;
          data.isScrolling = spswiper.isHorizontal() ? touchAngle > params.touchAngle : 90 - touchAngle > params.touchAngle;
        }
      }
    }
    if (data.isScrolling) {
      spswiper.emit('touchMoveOpposite', e);
    }
    if (typeof data.startMoving === 'undefined') {
      if (touches.currentX !== touches.startX || touches.currentY !== touches.startY) {
        data.startMoving = true;
      }
    }
    if (data.isScrolling || spswiper.zoom && spswiper.params.zoom && spswiper.params.zoom.enabled && data.evCache.length > 1) {
      data.isTouched = false;
      return;
    }
    if (!data.startMoving) {
      return;
    }
    spswiper.allowClick = false;
    if (!params.cssMode && e.cancelable) {
      e.preventDefault();
    }
    if (params.touchMoveStopPropagation && !params.nested) {
      e.stopPropagation();
    }
    let diff = spswiper.isHorizontal() ? diffX : diffY;
    let touchesDiff = spswiper.isHorizontal() ? touches.currentX - touches.previousX : touches.currentY - touches.previousY;
    if (params.oneWayMovement) {
      diff = Math.abs(diff) * (rtl ? 1 : -1);
      touchesDiff = Math.abs(touchesDiff) * (rtl ? 1 : -1);
    }
    touches.diff = diff;
    diff *= params.touchRatio;
    if (rtl) {
      diff = -diff;
      touchesDiff = -touchesDiff;
    }
    const prevTouchesDirection = spswiper.touchesDirection;
    spswiper.swipeDirection = diff > 0 ? 'prev' : 'next';
    spswiper.touchesDirection = touchesDiff > 0 ? 'prev' : 'next';
    const isLoop = spswiper.params.loop && !params.cssMode;
    const allowLoopFix = spswiper.swipeDirection === 'next' && spswiper.allowSlideNext || spswiper.swipeDirection === 'prev' && spswiper.allowSlidePrev;
    if (!data.isMoved) {
      if (isLoop && allowLoopFix) {
        spswiper.loopFix({
          direction: spswiper.swipeDirection
        });
      }
      data.startTranslate = spswiper.getTranslate();
      spswiper.setTransition(0);
      if (spswiper.animating) {
        const evt = new window.CustomEvent('transitionend', {
          bubbles: true,
          cancelable: true
        });
        spswiper.wrapperEl.dispatchEvent(evt);
      }
      data.allowMomentumBounce = false;
      // Grab Cursor
      if (params.grabCursor && (spswiper.allowSlideNext === true || spswiper.allowSlidePrev === true)) {
        spswiper.setGrabCursor(true);
      }
      spswiper.emit('sliderFirstMove', e);
    }
    let loopFixed;
    if (data.isMoved && prevTouchesDirection !== spswiper.touchesDirection && isLoop && allowLoopFix && Math.abs(diff) >= 1) {
      // need another loop fix
      spswiper.loopFix({
        direction: spswiper.swipeDirection,
        setTranslate: true
      });
      loopFixed = true;
    }
    spswiper.emit('sliderMove', e);
    data.isMoved = true;
    data.currentTranslate = diff + data.startTranslate;
    let disableParentSPSwiper = true;
    let resistanceRatio = params.resistanceRatio;
    if (params.touchReleaseOnEdges) {
      resistanceRatio = 0;
    }
    if (diff > 0) {
      if (isLoop && allowLoopFix && !loopFixed && data.currentTranslate > (params.centeredSlides ? spswiper.minTranslate() - spswiper.size / 2 : spswiper.minTranslate())) {
        spswiper.loopFix({
          direction: 'prev',
          setTranslate: true,
          activeSlideIndex: 0
        });
      }
      if (data.currentTranslate > spswiper.minTranslate()) {
        disableParentSPSwiper = false;
        if (params.resistance) {
          data.currentTranslate = spswiper.minTranslate() - 1 + (-spswiper.minTranslate() + data.startTranslate + diff) ** resistanceRatio;
        }
      }
    } else if (diff < 0) {
      if (isLoop && allowLoopFix && !loopFixed && data.currentTranslate < (params.centeredSlides ? spswiper.maxTranslate() + spswiper.size / 2 : spswiper.maxTranslate())) {
        spswiper.loopFix({
          direction: 'next',
          setTranslate: true,
          activeSlideIndex: spswiper.slides.length - (params.slidesPerView === 'auto' ? spswiper.slidesPerViewDynamic() : Math.ceil(parseFloat(params.slidesPerView, 10)))
        });
      }
      if (data.currentTranslate < spswiper.maxTranslate()) {
        disableParentSPSwiper = false;
        if (params.resistance) {
          data.currentTranslate = spswiper.maxTranslate() + 1 - (spswiper.maxTranslate() - data.startTranslate - diff) ** resistanceRatio;
        }
      }
    }
    if (disableParentSPSwiper) {
      e.preventedByNestedSPSwiper = true;
    }

    // Directions locks
    if (!spswiper.allowSlideNext && spswiper.swipeDirection === 'next' && data.currentTranslate < data.startTranslate) {
      data.currentTranslate = data.startTranslate;
    }
    if (!spswiper.allowSlidePrev && spswiper.swipeDirection === 'prev' && data.currentTranslate > data.startTranslate) {
      data.currentTranslate = data.startTranslate;
    }
    if (!spswiper.allowSlidePrev && !spswiper.allowSlideNext) {
      data.currentTranslate = data.startTranslate;
    }

    // Threshold
    if (params.threshold > 0) {
      if (Math.abs(diff) > params.threshold || data.allowThresholdMove) {
        if (!data.allowThresholdMove) {
          data.allowThresholdMove = true;
          touches.startX = touches.currentX;
          touches.startY = touches.currentY;
          data.currentTranslate = data.startTranslate;
          touches.diff = spswiper.isHorizontal() ? touches.currentX - touches.startX : touches.currentY - touches.startY;
          return;
        }
      } else {
        data.currentTranslate = data.startTranslate;
        return;
      }
    }
    if (!params.followFinger || params.cssMode) return;

    // Update active index in free mode
    if (params.freeMode && params.freeMode.enabled && spswiper.freeMode || params.watchSlidesProgress) {
      spswiper.updateActiveIndex();
      spswiper.updateSlidesClasses();
    }
    if (params.freeMode && params.freeMode.enabled && spswiper.freeMode) {
      spswiper.freeMode.onTouchMove();
    }
    // Update progress
    spswiper.updateProgress(data.currentTranslate);
    // Update translate
    spswiper.setTranslate(data.currentTranslate);
  }

  function onTouchEnd(event) {
    const spswiper = this;
    const data = spswiper.touchEventsData;
    const pointerIndex = data.evCache.findIndex(cachedEv => cachedEv.pointerId === event.pointerId);
    if (pointerIndex >= 0) {
      data.evCache.splice(pointerIndex, 1);
    }
    if (['pointercancel', 'pointerout', 'pointerleave', 'contextmenu'].includes(event.type)) {
      const proceed = ['pointercancel', 'contextmenu'].includes(event.type) && (spswiper.browser.isSafari || spswiper.browser.isWebView);
      if (!proceed) {
        return;
      }
    }
    const {
      params,
      touches,
      rtlTranslate: rtl,
      slidesGrid,
      enabled
    } = spswiper;
    if (!enabled) return;
    if (!params.simulateTouch && event.pointerType === 'mouse') return;
    let e = event;
    if (e.originalEvent) e = e.originalEvent;
    if (data.allowTouchCallbacks) {
      spswiper.emit('touchEnd', e);
    }
    data.allowTouchCallbacks = false;
    if (!data.isTouched) {
      if (data.isMoved && params.grabCursor) {
        spswiper.setGrabCursor(false);
      }
      data.isMoved = false;
      data.startMoving = false;
      return;
    }
    // Return Grab Cursor
    if (params.grabCursor && data.isMoved && data.isTouched && (spswiper.allowSlideNext === true || spswiper.allowSlidePrev === true)) {
      spswiper.setGrabCursor(false);
    }

    // Time diff
    const touchEndTime = now();
    const timeDiff = touchEndTime - data.touchStartTime;

    // Tap, doubleTap, Click
    if (spswiper.allowClick) {
      const pathTree = e.path || e.composedPath && e.composedPath();
      spswiper.updateClickedSlide(pathTree && pathTree[0] || e.target, pathTree);
      spswiper.emit('tap click', e);
      if (timeDiff < 300 && touchEndTime - data.lastClickTime < 300) {
        spswiper.emit('doubleTap doubleClick', e);
      }
    }
    data.lastClickTime = now();
    nextTick(() => {
      if (!spswiper.destroyed) spswiper.allowClick = true;
    });
    if (!data.isTouched || !data.isMoved || !spswiper.swipeDirection || touches.diff === 0 || data.currentTranslate === data.startTranslate) {
      data.isTouched = false;
      data.isMoved = false;
      data.startMoving = false;
      return;
    }
    data.isTouched = false;
    data.isMoved = false;
    data.startMoving = false;
    let currentPos;
    if (params.followFinger) {
      currentPos = rtl ? spswiper.translate : -spswiper.translate;
    } else {
      currentPos = -data.currentTranslate;
    }
    if (params.cssMode) {
      return;
    }
    if (params.freeMode && params.freeMode.enabled) {
      spswiper.freeMode.onTouchEnd({
        currentPos
      });
      return;
    }

    // Find current slide
    let stopIndex = 0;
    let groupSize = spswiper.slidesSizesGrid[0];
    for (let i = 0; i < slidesGrid.length; i += i < params.slidesPerGroupSkip ? 1 : params.slidesPerGroup) {
      const increment = i < params.slidesPerGroupSkip - 1 ? 1 : params.slidesPerGroup;
      if (typeof slidesGrid[i + increment] !== 'undefined') {
        if (currentPos >= slidesGrid[i] && currentPos < slidesGrid[i + increment]) {
          stopIndex = i;
          groupSize = slidesGrid[i + increment] - slidesGrid[i];
        }
      } else if (currentPos >= slidesGrid[i]) {
        stopIndex = i;
        groupSize = slidesGrid[slidesGrid.length - 1] - slidesGrid[slidesGrid.length - 2];
      }
    }
    let rewindFirstIndex = null;
    let rewindLastIndex = null;
    if (params.rewind) {
      if (spswiper.isBeginning) {
        rewindLastIndex = params.virtual && params.virtual.enabled && spswiper.virtual ? spswiper.virtual.slides.length - 1 : spswiper.slides.length - 1;
      } else if (spswiper.isEnd) {
        rewindFirstIndex = 0;
      }
    }
    // Find current slide size
    const ratio = (currentPos - slidesGrid[stopIndex]) / groupSize;
    const increment = stopIndex < params.slidesPerGroupSkip - 1 ? 1 : params.slidesPerGroup;
    if (timeDiff > params.longSwipesMs) {
      // Long touches
      if (!params.longSwipes) {
        spswiper.slideTo(spswiper.activeIndex);
        return;
      }
      if (spswiper.swipeDirection === 'next') {
        if (ratio >= params.longSwipesRatio) spswiper.slideTo(params.rewind && spswiper.isEnd ? rewindFirstIndex : stopIndex + increment);else spswiper.slideTo(stopIndex);
      }
      if (spswiper.swipeDirection === 'prev') {
        if (ratio > 1 - params.longSwipesRatio) {
          spswiper.slideTo(stopIndex + increment);
        } else if (rewindLastIndex !== null && ratio < 0 && Math.abs(ratio) > params.longSwipesRatio) {
          spswiper.slideTo(rewindLastIndex);
        } else {
          spswiper.slideTo(stopIndex);
        }
      }
    } else {
      // Short swipes
      if (!params.shortSwipes) {
        spswiper.slideTo(spswiper.activeIndex);
        return;
      }
      const isNavButtonTarget = spswiper.navigation && (e.target === spswiper.navigation.nextEl || e.target === spswiper.navigation.prevEl);
      if (!isNavButtonTarget) {
        if (spswiper.swipeDirection === 'next') {
          spswiper.slideTo(rewindFirstIndex !== null ? rewindFirstIndex : stopIndex + increment);
        }
        if (spswiper.swipeDirection === 'prev') {
          spswiper.slideTo(rewindLastIndex !== null ? rewindLastIndex : stopIndex);
        }
      } else if (e.target === spswiper.navigation.nextEl) {
        spswiper.slideTo(stopIndex + increment);
      } else {
        spswiper.slideTo(stopIndex);
      }
    }
  }

  function onResize() {
    const spswiper = this;
    const {
      params,
      el
    } = spswiper;
    if (el && el.offsetWidth === 0) return;

    // Breakpoints
    if (params.breakpoints) {
      spswiper.setBreakpoint();
    }

    // Save locks
    const {
      allowSlideNext,
      allowSlidePrev,
      snapGrid
    } = spswiper;
    const isVirtual = spswiper.virtual && spswiper.params.virtual.enabled;

    // Disable locks on resize
    spswiper.allowSlideNext = true;
    spswiper.allowSlidePrev = true;
    spswiper.updateSize();
    spswiper.updateSlides();
    spswiper.updateSlidesClasses();
    const isVirtualLoop = isVirtual && params.loop;
    if ((params.slidesPerView === 'auto' || params.slidesPerView > 1) && spswiper.isEnd && !spswiper.isBeginning && !spswiper.params.centeredSlides && !isVirtualLoop) {
      spswiper.slideTo(spswiper.slides.length - 1, 0, false, true);
    } else {
      if (spswiper.params.loop && !isVirtual) {
        spswiper.slideToLoop(spswiper.realIndex, 0, false, true);
      } else {
        spswiper.slideTo(spswiper.activeIndex, 0, false, true);
      }
    }
    if (spswiper.autoplay && spswiper.autoplay.running && spswiper.autoplay.paused) {
      clearTimeout(spswiper.autoplay.resizeTimeout);
      spswiper.autoplay.resizeTimeout = setTimeout(() => {
        if (spswiper.autoplay && spswiper.autoplay.running && spswiper.autoplay.paused) {
          spswiper.autoplay.resume();
        }
      }, 500);
    }
    // Return locks after resize
    spswiper.allowSlidePrev = allowSlidePrev;
    spswiper.allowSlideNext = allowSlideNext;
    if (spswiper.params.watchOverflow && snapGrid !== spswiper.snapGrid) {
      spswiper.checkOverflow();
    }
  }

  function onClick(e) {
    const spswiper = this;
    if (!spswiper.enabled) return;
    if (!spswiper.allowClick) {
      if (spswiper.params.preventClicks) e.preventDefault();
      if (spswiper.params.preventClicksPropagation && spswiper.animating) {
        e.stopPropagation();
        e.stopImmediatePropagation();
      }
    }
  }

  function onScroll() {
    const spswiper = this;
    const {
      wrapperEl,
      rtlTranslate,
      enabled
    } = spswiper;
    if (!enabled) return;
    spswiper.previousTranslate = spswiper.translate;
    if (spswiper.isHorizontal()) {
      spswiper.translate = -wrapperEl.scrollLeft;
    } else {
      spswiper.translate = -wrapperEl.scrollTop;
    }
    // eslint-disable-next-line
    if (spswiper.translate === 0) spswiper.translate = 0;
    spswiper.updateActiveIndex();
    spswiper.updateSlidesClasses();
    let newProgress;
    const translatesDiff = spswiper.maxTranslate() - spswiper.minTranslate();
    if (translatesDiff === 0) {
      newProgress = 0;
    } else {
      newProgress = (spswiper.translate - spswiper.minTranslate()) / translatesDiff;
    }
    if (newProgress !== spswiper.progress) {
      spswiper.updateProgress(rtlTranslate ? -spswiper.translate : spswiper.translate);
    }
    spswiper.emit('setTranslate', spswiper.translate, false);
  }

  function onLoad(e) {
    const spswiper = this;
    processLazyPreloader(spswiper, e.target);
    if (spswiper.params.cssMode || spswiper.params.slidesPerView !== 'auto' && !spswiper.params.autoHeight) {
      return;
    }
    spswiper.update();
  }

  let dummyEventAttached = false;
  function dummyEventListener() {}
  const events = (spswiper, method) => {
    const document = getDocument();
    const {
      params,
      el,
      wrapperEl,
      device
    } = spswiper;
    const capture = !!params.nested;
    const domMethod = method === 'on' ? 'addEventListener' : 'removeEventListener';
    const spswiperMethod = method;

    // Touch Events
    el[domMethod]('pointerdown', spswiper.onTouchStart, {
      passive: false
    });
    document[domMethod]('pointermove', spswiper.onTouchMove, {
      passive: false,
      capture
    });
    document[domMethod]('pointerup', spswiper.onTouchEnd, {
      passive: true
    });
    document[domMethod]('pointercancel', spswiper.onTouchEnd, {
      passive: true
    });
    document[domMethod]('pointerout', spswiper.onTouchEnd, {
      passive: true
    });
    document[domMethod]('pointerleave', spswiper.onTouchEnd, {
      passive: true
    });
    document[domMethod]('contextmenu', spswiper.onTouchEnd, {
      passive: true
    });

    // Prevent Links Clicks
    if (params.preventClicks || params.preventClicksPropagation) {
      el[domMethod]('click', spswiper.onClick, true);
    }
    if (params.cssMode) {
      wrapperEl[domMethod]('scroll', spswiper.onScroll);
    }

    // Resize handler
    if (params.updateOnWindowResize) {
      spswiper[spswiperMethod](device.ios || device.android ? 'resize orientationchange observerUpdate' : 'resize observerUpdate', onResize, true);
    } else {
      spswiper[spswiperMethod]('observerUpdate', onResize, true);
    }

    // Images loader
    el[domMethod]('load', spswiper.onLoad, {
      capture: true
    });
  };
  function attachEvents() {
    const spswiper = this;
    const document = getDocument();
    const {
      params
    } = spswiper;
    spswiper.onTouchStart = onTouchStart.bind(spswiper);
    spswiper.onTouchMove = onTouchMove.bind(spswiper);
    spswiper.onTouchEnd = onTouchEnd.bind(spswiper);
    if (params.cssMode) {
      spswiper.onScroll = onScroll.bind(spswiper);
    }
    spswiper.onClick = onClick.bind(spswiper);
    spswiper.onLoad = onLoad.bind(spswiper);
    if (!dummyEventAttached) {
      document.addEventListener('touchstart', dummyEventListener);
      dummyEventAttached = true;
    }
    events(spswiper, 'on');
  }
  function detachEvents() {
    const spswiper = this;
    events(spswiper, 'off');
  }
  var events$1 = {
    attachEvents,
    detachEvents
  };

  const isGridEnabled = (spswiper, params) => {
    return spswiper.grid && params.grid && params.grid.rows > 1;
  };
  function setBreakpoint() {
    const spswiper = this;
    const {
      realIndex,
      initialized,
      params,
      el
    } = spswiper;
    const breakpoints = params.breakpoints;
    if (!breakpoints || breakpoints && Object.keys(breakpoints).length === 0) return;

    // Get breakpoint for window width and update parameters
    const breakpoint = spswiper.getBreakpoint(breakpoints, spswiper.params.breakpointsBase, spswiper.el);
    if (!breakpoint || spswiper.currentBreakpoint === breakpoint) return;
    const breakpointOnlyParams = breakpoint in breakpoints ? breakpoints[breakpoint] : undefined;
    const breakpointParams = breakpointOnlyParams || spswiper.originalParams;
    const wasMultiRow = isGridEnabled(spswiper, params);
    const isMultiRow = isGridEnabled(spswiper, breakpointParams);
    const wasEnabled = params.enabled;
    if (wasMultiRow && !isMultiRow) {
      el.classList.remove(`${params.containerModifierClass}grid`, `${params.containerModifierClass}grid-column`);
      spswiper.emitContainerClasses();
    } else if (!wasMultiRow && isMultiRow) {
      el.classList.add(`${params.containerModifierClass}grid`);
      if (breakpointParams.grid.fill && breakpointParams.grid.fill === 'column' || !breakpointParams.grid.fill && params.grid.fill === 'column') {
        el.classList.add(`${params.containerModifierClass}grid-column`);
      }
      spswiper.emitContainerClasses();
    }

    // Toggle navigation, pagination, scrollbar
    ['navigation', 'pagination', 'scrollbar'].forEach(prop => {
      if (typeof breakpointParams[prop] === 'undefined') return;
      const wasModuleEnabled = params[prop] && params[prop].enabled;
      const isModuleEnabled = breakpointParams[prop] && breakpointParams[prop].enabled;
      if (wasModuleEnabled && !isModuleEnabled) {
        spswiper[prop].disable();
      }
      if (!wasModuleEnabled && isModuleEnabled) {
        spswiper[prop].enable();
      }
    });
    const directionChanged = breakpointParams.direction && breakpointParams.direction !== params.direction;
    const needsReLoop = params.loop && (breakpointParams.slidesPerView !== params.slidesPerView || directionChanged);
    const wasLoop = params.loop;
    if (directionChanged && initialized) {
      spswiper.changeDirection();
    }
    extend(spswiper.params, breakpointParams);
    const isEnabled = spswiper.params.enabled;
    const hasLoop = spswiper.params.loop;
    Object.assign(spswiper, {
      allowTouchMove: spswiper.params.allowTouchMove,
      allowSlideNext: spswiper.params.allowSlideNext,
      allowSlidePrev: spswiper.params.allowSlidePrev
    });
    if (wasEnabled && !isEnabled) {
      spswiper.disable();
    } else if (!wasEnabled && isEnabled) {
      spswiper.enable();
    }
    spswiper.currentBreakpoint = breakpoint;
    spswiper.emit('_beforeBreakpoint', breakpointParams);
    if (initialized) {
      if (needsReLoop) {
        spswiper.loopDestroy();
        spswiper.loopCreate(realIndex);
        spswiper.updateSlides();
      } else if (!wasLoop && hasLoop) {
        spswiper.loopCreate(realIndex);
        spswiper.updateSlides();
      } else if (wasLoop && !hasLoop) {
        spswiper.loopDestroy();
      }
    }
    spswiper.emit('breakpoint', breakpointParams);
  }

  function getBreakpoint(breakpoints, base, containerEl) {
    if (base === void 0) {
      base = 'window';
    }
    if (!breakpoints || base === 'container' && !containerEl) return undefined;
    let breakpoint = false;
    const window = getWindow();
    const currentHeight = base === 'window' ? window.innerHeight : containerEl.clientHeight;
    const points = Object.keys(breakpoints).map(point => {
      if (typeof point === 'string' && point.indexOf('@') === 0) {
        const minRatio = parseFloat(point.substr(1));
        const value = currentHeight * minRatio;
        return {
          value,
          point
        };
      }
      return {
        value: point,
        point
      };
    });
    points.sort((a, b) => parseInt(a.value, 10) - parseInt(b.value, 10));
    for (let i = 0; i < points.length; i += 1) {
      const {
        point,
        value
      } = points[i];
      if (base === 'window') {
        if (window.matchMedia(`(min-width: ${value}px)`).matches) {
          breakpoint = point;
        }
      } else if (value <= containerEl.clientWidth) {
        breakpoint = point;
      }
    }
    return breakpoint || 'max';
  }

  var breakpoints = {
    setBreakpoint,
    getBreakpoint
  };

  function prepareClasses(entries, prefix) {
    const resultClasses = [];
    entries.forEach(item => {
      if (typeof item === 'object') {
        Object.keys(item).forEach(classNames => {
          if (item[classNames]) {
            resultClasses.push(prefix + classNames);
          }
        });
      } else if (typeof item === 'string') {
        resultClasses.push(prefix + item);
      }
    });
    return resultClasses;
  }
  function addClasses() {
    const spswiper = this;
    const {
      classNames,
      params,
      rtl,
      el,
      device
    } = spswiper;
    // prettier-ignore
    const suffixes = prepareClasses(['initialized', params.direction, {
      'free-mode': spswiper.params.freeMode && params.freeMode.enabled
    }, {
      'autoheight': params.autoHeight
    }, {
      'rtl': rtl
    }, {
      'grid': params.grid && params.grid.rows > 1
    }, {
      'grid-column': params.grid && params.grid.rows > 1 && params.grid.fill === 'column'
    }, {
      'android': device.android
    }, {
      'ios': device.ios
    }, {
      'css-mode': params.cssMode
    }, {
      'centered': params.cssMode && params.centeredSlides
    }, {
      'watch-progress': params.watchSlidesProgress
    }], params.containerModifierClass);
    classNames.push(...suffixes);
    el.classList.add(...classNames);
    spswiper.emitContainerClasses();
  }

  function removeClasses() {
    const spswiper = this;
    const {
      el,
      classNames
    } = spswiper;
    el.classList.remove(...classNames);
    spswiper.emitContainerClasses();
  }

  var classes = {
    addClasses,
    removeClasses
  };

  function checkOverflow() {
    const spswiper = this;
    const {
      isLocked: wasLocked,
      params
    } = spswiper;
    const {
      slidesOffsetBefore
    } = params;
    if (slidesOffsetBefore) {
      const lastSlideIndex = spswiper.slides.length - 1;
      const lastSlideRightEdge = spswiper.slidesGrid[lastSlideIndex] + spswiper.slidesSizesGrid[lastSlideIndex] + slidesOffsetBefore * 2;
      spswiper.isLocked = spswiper.size > lastSlideRightEdge;
    } else {
      spswiper.isLocked = spswiper.snapGrid.length === 1;
    }
    if (params.allowSlideNext === true) {
      spswiper.allowSlideNext = !spswiper.isLocked;
    }
    if (params.allowSlidePrev === true) {
      spswiper.allowSlidePrev = !spswiper.isLocked;
    }
    if (wasLocked && wasLocked !== spswiper.isLocked) {
      spswiper.isEnd = false;
    }
    if (wasLocked !== spswiper.isLocked) {
      spswiper.emit(spswiper.isLocked ? 'lock' : 'unlock');
    }
  }
  var checkOverflow$1 = {
    checkOverflow
  };

  var defaults = {
    init: true,
    direction: 'horizontal',
    oneWayMovement: false,
    touchEventsTarget: 'wrapper',
    initialSlide: 0,
    speed: 300,
    cssMode: false,
    updateOnWindowResize: true,
    resizeObserver: true,
    nested: false,
    createElements: false,
    enabled: true,
    focusableElements: 'input, select, option, textarea, button, video, label',
    // Overrides
    width: null,
    height: null,
    //
    preventInteractionOnTransition: false,
    // ssr
    userAgent: null,
    url: null,
    // To support iOS's swipe-to-go-back gesture (when being used in-app).
    edgeSwipeDetection: false,
    edgeSwipeThreshold: 20,
    // Autoheight
    autoHeight: false,
    // Set wrapper width
    setWrapperSize: false,
    // Virtual Translate
    virtualTranslate: false,
    // Effects
    effect: 'slide',
    // 'slide' or 'fade' or 'cube' or 'coverflow' or 'flip'

    // Breakpoints
    breakpoints: undefined,
    breakpointsBase: 'window',
    // Slides grid
    spaceBetween: 0,
    slidesPerView: 1,
    slidesPerGroup: 1,
    slidesPerGroupSkip: 0,
    slidesPerGroupAuto: false,
    centeredSlides: false,
    centeredSlidesBounds: false,
    slidesOffsetBefore: 0,
    // in px
    slidesOffsetAfter: 0,
    // in px
    normalizeSlideIndex: true,
    centerInsufficientSlides: false,
    // Disable spswiper and hide navigation when container not overflow
    watchOverflow: true,
    // Round length
    roundLengths: false,
    // Touches
    touchRatio: 1,
    touchAngle: 45,
    simulateTouch: true,
    shortSwipes: true,
    longSwipes: true,
    longSwipesRatio: 0.5,
    longSwipesMs: 300,
    followFinger: true,
    allowTouchMove: true,
    threshold: 5,
    touchMoveStopPropagation: false,
    touchStartPreventDefault: true,
    touchStartForcePreventDefault: false,
    touchReleaseOnEdges: false,
    // Unique Navigation Elements
    uniqueNavElements: true,
    // Resistance
    resistance: true,
    resistanceRatio: 0.85,
    // Progress
    watchSlidesProgress: false,
    // Cursor
    grabCursor: false,
    // Clicks
    preventClicks: true,
    preventClicksPropagation: true,
    slideToClickedSlide: false,
    // loop
    loop: false,
    loopedSlides: null,
    loopPreventsSliding: true,
    // rewind
    rewind: false,
    // Swiping/no swiping
    allowSlidePrev: true,
    allowSlideNext: true,
    swipeHandler: null,
    // '.swipe-handler',
    noSwiping: true,
    noSwipingClass: 'spswiper-no-swiping',
    noSwipingSelector: null,
    // Passive Listeners
    passiveListeners: true,
    maxBackfaceHiddenSlides: 10,
    // NS
    containerModifierClass: 'spswiper-',
    // NEW
    slideClass: 'spswiper-slide',
    slideActiveClass: 'spswiper-slide-active',
    slideVisibleClass: 'spswiper-slide-visible',
    slideNextClass: 'spswiper-slide-next',
    slidePrevClass: 'spswiper-slide-prev',
    wrapperClass: 'spswiper-wrapper',
    lazyPreloaderClass: 'spswiper-lazy-preloader',
    lazyPreloadPrevNext: 0,
    // Callbacks
    runCallbacksOnInit: true,
    // Internals
    _emitClasses: false
  };

  function moduleExtendParams(params, allModulesParams) {
    return function extendParams(obj) {
      if (obj === void 0) {
        obj = {};
      }
      const moduleParamName = Object.keys(obj)[0];
      const moduleParams = obj[moduleParamName];
      if (typeof moduleParams !== 'object' || moduleParams === null) {
        extend(allModulesParams, obj);
        return;
      }
      if (params[moduleParamName] === true) {
        params[moduleParamName] = {
          enabled: true
        };
      }
      if (moduleParamName === 'navigation' && params[moduleParamName] && params[moduleParamName].enabled && !params[moduleParamName].prevEl && !params[moduleParamName].nextEl) {
        params[moduleParamName].auto = true;
      }
      if (['pagination', 'scrollbar'].indexOf(moduleParamName) >= 0 && params[moduleParamName] && params[moduleParamName].enabled && !params[moduleParamName].el) {
        params[moduleParamName].auto = true;
      }
      if (!(moduleParamName in params && 'enabled' in moduleParams)) {
        extend(allModulesParams, obj);
        return;
      }
      if (typeof params[moduleParamName] === 'object' && !('enabled' in params[moduleParamName])) {
        params[moduleParamName].enabled = true;
      }
      if (!params[moduleParamName]) params[moduleParamName] = {
        enabled: false
      };
      extend(allModulesParams, obj);
    };
  }

  /* eslint no-param-reassign: "off" */
  const prototypes = {
    eventsEmitter,
    update,
    translate,
    transition,
    slide,
    loop,
    grabCursor,
    events: events$1,
    breakpoints,
    checkOverflow: checkOverflow$1,
    classes
  };
  const extendedDefaults = {};
  class SPSwiper {
    constructor() {
      let el;
      let params;
      for (var _len = arguments.length, args = new Array(_len), _key = 0; _key < _len; _key++) {
        args[_key] = arguments[_key];
      }
      if (args.length === 1 && args[0].constructor && Object.prototype.toString.call(args[0]).slice(8, -1) === 'Object') {
        params = args[0];
      } else {
        [el, params] = args;
      }
      if (!params) params = {};
      params = extend({}, params);
      if (el && !params.el) params.el = el;
      const document = getDocument();
      if (params.el && typeof params.el === 'string' && document.querySelectorAll(params.el).length > 1) {
        const spswipers = [];
        document.querySelectorAll(params.el).forEach(containerEl => {
          const newParams = extend({}, params, {
            el: containerEl
          });
          spswipers.push(new SPSwiper(newParams));
        });
        // eslint-disable-next-line no-constructor-return
        return spswipers;
      }

      // SPSwiper Instance
      const spswiper = this;
      spswiper.__spswiper__ = true;
      spswiper.support = getSupport();
      spswiper.device = getDevice({
        userAgent: params.userAgent
      });
      spswiper.browser = getBrowser();
      spswiper.eventsListeners = {};
      spswiper.eventsAnyListeners = [];
      spswiper.modules = [...spswiper.__modules__];
      if (params.modules && Array.isArray(params.modules)) {
        spswiper.modules.push(...params.modules);
      }
      const allModulesParams = {};
      spswiper.modules.forEach(mod => {
        mod({
          params,
          spswiper,
          extendParams: moduleExtendParams(params, allModulesParams),
          on: spswiper.on.bind(spswiper),
          once: spswiper.once.bind(spswiper),
          off: spswiper.off.bind(spswiper),
          emit: spswiper.emit.bind(spswiper)
        });
      });

      // Extend defaults with modules params
      const spswiperParams = extend({}, defaults, allModulesParams);

      // Extend defaults with passed params
      spswiper.params = extend({}, spswiperParams, extendedDefaults, params);
      spswiper.originalParams = extend({}, spswiper.params);
      spswiper.passedParams = extend({}, params);

      // add event listeners
      if (spswiper.params && spswiper.params.on) {
        Object.keys(spswiper.params.on).forEach(eventName => {
          spswiper.on(eventName, spswiper.params.on[eventName]);
        });
      }
      if (spswiper.params && spswiper.params.onAny) {
        spswiper.onAny(spswiper.params.onAny);
      }

      // Extend SPSwiper
      Object.assign(spswiper, {
        enabled: spswiper.params.enabled,
        el,
        // Classes
        classNames: [],
        // Slides
        slides: [],
        slidesGrid: [],
        snapGrid: [],
        slidesSizesGrid: [],
        // isDirection
        isHorizontal() {
          return spswiper.params.direction === 'horizontal';
        },
        isVertical() {
          return spswiper.params.direction === 'vertical';
        },
        // Indexes
        activeIndex: 0,
        realIndex: 0,
        //
        isBeginning: true,
        isEnd: false,
        // Props
        translate: 0,
        previousTranslate: 0,
        progress: 0,
        velocity: 0,
        animating: false,
        cssOverflowAdjustment() {
          // Returns 0 unless `translate` is > 2**23
          // Should be subtracted from css values to prevent overflow
          return Math.trunc(this.translate / 2 ** 23) * 2 ** 23;
        },
        // Locks
        allowSlideNext: spswiper.params.allowSlideNext,
        allowSlidePrev: spswiper.params.allowSlidePrev,
        // Touch Events
        touchEventsData: {
          isTouched: undefined,
          isMoved: undefined,
          allowTouchCallbacks: undefined,
          touchStartTime: undefined,
          isScrolling: undefined,
          currentTranslate: undefined,
          startTranslate: undefined,
          allowThresholdMove: undefined,
          // Form elements to match
          focusableElements: spswiper.params.focusableElements,
          // Last click time
          lastClickTime: 0,
          clickTimeout: undefined,
          // Velocities
          velocities: [],
          allowMomentumBounce: undefined,
          startMoving: undefined,
          evCache: []
        },
        // Clicks
        allowClick: true,
        // Touches
        allowTouchMove: spswiper.params.allowTouchMove,
        touches: {
          startX: 0,
          startY: 0,
          currentX: 0,
          currentY: 0,
          diff: 0
        },
        // Images
        imagesToLoad: [],
        imagesLoaded: 0
      });
      spswiper.emit('_spswiper');

      // Init
      if (spswiper.params.init) {
        spswiper.init();
      }

      // Return app instance
      // eslint-disable-next-line no-constructor-return
      return spswiper;
    }
    getSlideIndex(slideEl) {
      const {
        slidesEl,
        params
      } = this;
      const slides = elementChildren(slidesEl, `.${params.slideClass}, spswiper-slide`);
      const firstSlideIndex = elementIndex(slides[0]);
      return elementIndex(slideEl) - firstSlideIndex;
    }
    getSlideIndexByData(index) {
      return this.getSlideIndex(this.slides.filter(slideEl => slideEl.getAttribute('data-spswiper-slide-index') * 1 === index)[0]);
    }
    recalcSlides() {
      const spswiper = this;
      const {
        slidesEl,
        params
      } = spswiper;
      spswiper.slides = elementChildren(slidesEl, `.${params.slideClass}, spswiper-slide`);
    }
    enable() {
      const spswiper = this;
      if (spswiper.enabled) return;
      spswiper.enabled = true;
      if (spswiper.params.grabCursor) {
        spswiper.setGrabCursor();
      }
      spswiper.emit('enable');
    }
    disable() {
      const spswiper = this;
      if (!spswiper.enabled) return;
      spswiper.enabled = false;
      if (spswiper.params.grabCursor) {
        spswiper.unsetGrabCursor();
      }
      spswiper.emit('disable');
    }
    setProgress(progress, speed) {
      const spswiper = this;
      progress = Math.min(Math.max(progress, 0), 1);
      const min = spswiper.minTranslate();
      const max = spswiper.maxTranslate();
      const current = (max - min) * progress + min;
      spswiper.translateTo(current, typeof speed === 'undefined' ? 0 : speed);
      spswiper.updateActiveIndex();
      spswiper.updateSlidesClasses();
    }
    emitContainerClasses() {
      const spswiper = this;
      if (!spswiper.params._emitClasses || !spswiper.el) return;
      const cls = spswiper.el.className.split(' ').filter(className => {
        return className.indexOf('spswiper') === 0 || className.indexOf(spswiper.params.containerModifierClass) === 0;
      });
      spswiper.emit('_containerClasses', cls.join(' '));
    }
    getSlideClasses(slideEl) {
      const spswiper = this;
      if (spswiper.destroyed) return '';
      return slideEl.className.split(' ').filter(className => {
        return className.indexOf('spswiper-slide') === 0 || className.indexOf(spswiper.params.slideClass) === 0;
      }).join(' ');
    }
    emitSlidesClasses() {
      const spswiper = this;
      if (!spswiper.params._emitClasses || !spswiper.el) return;
      const updates = [];
      spswiper.slides.forEach(slideEl => {
        const classNames = spswiper.getSlideClasses(slideEl);
        updates.push({
          slideEl,
          classNames
        });
        spswiper.emit('_slideClass', slideEl, classNames);
      });
      spswiper.emit('_slideClasses', updates);
    }
    slidesPerViewDynamic(view, exact) {
      if (view === void 0) {
        view = 'current';
      }
      if (exact === void 0) {
        exact = false;
      }
      const spswiper = this;
      const {
        params,
        slides,
        slidesGrid,
        slidesSizesGrid,
        size: spswiperSize,
        activeIndex
      } = spswiper;
      let spv = 1;
      if (typeof params.slidesPerView === 'number') return params.slidesPerView;
      if (params.centeredSlides) {
        let slideSize = slides[activeIndex] ? slides[activeIndex].spswiperSlideSize : 0;
        let breakLoop;
        for (let i = activeIndex + 1; i < slides.length; i += 1) {
          if (slides[i] && !breakLoop) {
            slideSize += slides[i].spswiperSlideSize;
            spv += 1;
            if (slideSize > spswiperSize) breakLoop = true;
          }
        }
        for (let i = activeIndex - 1; i >= 0; i -= 1) {
          if (slides[i] && !breakLoop) {
            slideSize += slides[i].spswiperSlideSize;
            spv += 1;
            if (slideSize > spswiperSize) breakLoop = true;
          }
        }
      } else {
        // eslint-disable-next-line
        if (view === 'current') {
          for (let i = activeIndex + 1; i < slides.length; i += 1) {
            const slideInView = exact ? slidesGrid[i] + slidesSizesGrid[i] - slidesGrid[activeIndex] < spswiperSize : slidesGrid[i] - slidesGrid[activeIndex] < spswiperSize;
            if (slideInView) {
              spv += 1;
            }
          }
        } else {
          // previous
          for (let i = activeIndex - 1; i >= 0; i -= 1) {
            const slideInView = slidesGrid[activeIndex] - slidesGrid[i] < spswiperSize;
            if (slideInView) {
              spv += 1;
            }
          }
        }
      }
      return spv;
    }
    update() {
      const spswiper = this;
      if (!spswiper || spswiper.destroyed) return;
      const {
        snapGrid,
        params
      } = spswiper;
      // Breakpoints
      if (params.breakpoints) {
        spswiper.setBreakpoint();
      }
      [...spswiper.el.querySelectorAll('[loading="lazy"]')].forEach(imageEl => {
        if (imageEl.complete) {
          processLazyPreloader(spswiper, imageEl);
        }
      });
      spswiper.updateSize();
      spswiper.updateSlides();
      spswiper.updateProgress();
      spswiper.updateSlidesClasses();
      function setTranslate() {
        const translateValue = spswiper.rtlTranslate ? spswiper.translate * -1 : spswiper.translate;
        const newTranslate = Math.min(Math.max(translateValue, spswiper.maxTranslate()), spswiper.minTranslate());
        spswiper.setTranslate(newTranslate);
        spswiper.updateActiveIndex();
        spswiper.updateSlidesClasses();
      }
      let translated;
      if (params.freeMode && params.freeMode.enabled && !params.cssMode) {
        setTranslate();
        if (params.autoHeight) {
          spswiper.updateAutoHeight();
        }
      } else {
        if ((params.slidesPerView === 'auto' || params.slidesPerView > 1) && spswiper.isEnd && !params.centeredSlides) {
          const slides = spswiper.virtual && params.virtual.enabled ? spswiper.virtual.slides : spswiper.slides;
          translated = spswiper.slideTo(slides.length - 1, 0, false, true);
        } else {
          translated = spswiper.slideTo(spswiper.activeIndex, 0, false, true);
        }
        if (!translated) {
          setTranslate();
        }
      }
      if (params.watchOverflow && snapGrid !== spswiper.snapGrid) {
        spswiper.checkOverflow();
      }
      spswiper.emit('update');
    }
    changeDirection(newDirection, needUpdate) {
      if (needUpdate === void 0) {
        needUpdate = true;
      }
      const spswiper = this;
      const currentDirection = spswiper.params.direction;
      if (!newDirection) {
        // eslint-disable-next-line
        newDirection = currentDirection === 'horizontal' ? 'vertical' : 'horizontal';
      }
      if (newDirection === currentDirection || newDirection !== 'horizontal' && newDirection !== 'vertical') {
        return spswiper;
      }
      spswiper.el.classList.remove(`${spswiper.params.containerModifierClass}${currentDirection}`);
      spswiper.el.classList.add(`${spswiper.params.containerModifierClass}${newDirection}`);
      spswiper.emitContainerClasses();
      spswiper.params.direction = newDirection;
      spswiper.slides.forEach(slideEl => {
        if (newDirection === 'vertical') {
          slideEl.style.width = '';
        } else {
          slideEl.style.height = '';
        }
      });
      spswiper.emit('changeDirection');
      if (needUpdate) spswiper.update();
      return spswiper;
    }
    changeLanguageDirection(direction) {
      const spswiper = this;
      if (spswiper.rtl && direction === 'rtl' || !spswiper.rtl && direction === 'ltr') return;
      spswiper.rtl = direction === 'rtl';
      spswiper.rtlTranslate = spswiper.params.direction === 'horizontal' && spswiper.rtl;
      if (spswiper.rtl) {
        spswiper.el.classList.add(`${spswiper.params.containerModifierClass}rtl`);
        spswiper.el.dir = 'rtl';
      } else {
        spswiper.el.classList.remove(`${spswiper.params.containerModifierClass}rtl`);
        spswiper.el.dir = 'ltr';
      }
      spswiper.update();
    }
    mount(element) {
      const spswiper = this;
      if (spswiper.mounted) return true;

      // Find el
      let el = element || spswiper.params.el;
      if (typeof el === 'string') {
        el = document.querySelector(el);
      }
      if (!el) {
        return false;
      }
      el.spswiper = spswiper;
      if (el.parentNode && el.parentNode.host && el.parentNode.host.nodeName === 'SWIPER-CONTAINER') {
        spswiper.isElement = true;
      }
      const getWrapperSelector = () => {
        return `.${(spswiper.params.wrapperClass || '').trim().split(' ').join('.')}`;
      };
      const getWrapper = () => {
        if (el && el.shadowRoot && el.shadowRoot.querySelector) {
          const res = el.shadowRoot.querySelector(getWrapperSelector());
          // Children needs to return slot items
          return res;
        }
        return elementChildren(el, getWrapperSelector())[0];
      };
      // Find Wrapper
      let wrapperEl = getWrapper();
      if (!wrapperEl && spswiper.params.createElements) {
        wrapperEl = createElement('div', spswiper.params.wrapperClass);
        el.append(wrapperEl);
        elementChildren(el, `.${spswiper.params.slideClass}`).forEach(slideEl => {
          wrapperEl.append(slideEl);
        });
      }
      Object.assign(spswiper, {
        el,
        wrapperEl,
        slidesEl: spswiper.isElement && !el.parentNode.host.slideSlots ? el.parentNode.host : wrapperEl,
        hostEl: spswiper.isElement ? el.parentNode.host : el,
        mounted: true,
        // RTL
        rtl: el.dir.toLowerCase() === 'rtl' || elementStyle(el, 'direction') === 'rtl',
        rtlTranslate: spswiper.params.direction === 'horizontal' && (el.dir.toLowerCase() === 'rtl' || elementStyle(el, 'direction') === 'rtl'),
        wrongRTL: elementStyle(wrapperEl, 'display') === '-webkit-box'
      });
      return true;
    }
    init(el) {
      const spswiper = this;
      if (spswiper.initialized) return spswiper;
      const mounted = spswiper.mount(el);
      if (mounted === false) return spswiper;
      spswiper.emit('beforeInit');

      // Set breakpoint
      if (spswiper.params.breakpoints) {
        spswiper.setBreakpoint();
      }

      // Add Classes
      spswiper.addClasses();

      // Update size
      spswiper.updateSize();

      // Update slides
      spswiper.updateSlides();
      if (spswiper.params.watchOverflow) {
        spswiper.checkOverflow();
      }

      // Set Grab Cursor
      if (spswiper.params.grabCursor && spswiper.enabled) {
        spswiper.setGrabCursor();
      }

      // Slide To Initial Slide
      if (spswiper.params.loop && spswiper.virtual && spswiper.params.virtual.enabled) {
        spswiper.slideTo(spswiper.params.initialSlide + spswiper.virtual.slidesBefore, 0, spswiper.params.runCallbacksOnInit, false, true);
      } else {
        spswiper.slideTo(spswiper.params.initialSlide, 0, spswiper.params.runCallbacksOnInit, false, true);
      }

      // Create loop
      if (spswiper.params.loop) {
        spswiper.loopCreate();
      }

      // Attach events
      spswiper.attachEvents();
      const lazyElements = [...spswiper.el.querySelectorAll('[loading="lazy"]')];
      if (spswiper.isElement) {
        lazyElements.push(...spswiper.hostEl.querySelectorAll('[loading="lazy"]'));
      }
      lazyElements.forEach(imageEl => {
        if (imageEl.complete) {
          processLazyPreloader(spswiper, imageEl);
        } else {
          imageEl.addEventListener('load', e => {
            processLazyPreloader(spswiper, e.target);
          });
        }
      });
      preload(spswiper);

      // Init Flag
      spswiper.initialized = true;
      preload(spswiper);

      // Emit
      spswiper.emit('init');
      spswiper.emit('afterInit');
      return spswiper;
    }
    destroy(deleteInstance, cleanStyles) {
      if (deleteInstance === void 0) {
        deleteInstance = true;
      }
      if (cleanStyles === void 0) {
        cleanStyles = true;
      }
      const spswiper = this;
      const {
        params,
        el,
        wrapperEl,
        slides
      } = spswiper;
      if (typeof spswiper.params === 'undefined' || spswiper.destroyed) {
        return null;
      }
      spswiper.emit('beforeDestroy');

      // Init Flag
      spswiper.initialized = false;

      // Detach events
      spswiper.detachEvents();

      // Destroy loop
      if (params.loop) {
        spswiper.loopDestroy();
      }

      // Cleanup styles
      if (cleanStyles) {
        spswiper.removeClasses();
        el.removeAttribute('style');
        wrapperEl.removeAttribute('style');
        if (slides && slides.length) {
          slides.forEach(slideEl => {
            slideEl.classList.remove(params.slideVisibleClass, params.slideActiveClass, params.slideNextClass, params.slidePrevClass);
            slideEl.removeAttribute('style');
            slideEl.removeAttribute('data-spswiper-slide-index');
          });
        }
      }
      spswiper.emit('destroy');

      // Detach emitter events
      Object.keys(spswiper.eventsListeners).forEach(eventName => {
        spswiper.off(eventName);
      });
      if (deleteInstance !== false) {
        spswiper.el.spswiper = null;
        deleteProps(spswiper);
      }
      spswiper.destroyed = true;
      return null;
    }
    static extendDefaults(newDefaults) {
      extend(extendedDefaults, newDefaults);
    }
    static get extendedDefaults() {
      return extendedDefaults;
    }
    static get defaults() {
      return defaults;
    }
    static installModule(mod) {
      if (!SPSwiper.prototype.__modules__) SPSwiper.prototype.__modules__ = [];
      const modules = SPSwiper.prototype.__modules__;
      if (typeof mod === 'function' && modules.indexOf(mod) < 0) {
        modules.push(mod);
      }
    }
    static use(module) {
      if (Array.isArray(module)) {
        module.forEach(m => SPSwiper.installModule(m));
        return SPSwiper;
      }
      SPSwiper.installModule(module);
      return SPSwiper;
    }
  }
  Object.keys(prototypes).forEach(prototypeGroup => {
    Object.keys(prototypes[prototypeGroup]).forEach(protoMethod => {
      SPSwiper.prototype[protoMethod] = prototypes[prototypeGroup][protoMethod];
    });
  });
  SPSwiper.use([Resize, Observer]);

  function Virtual(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit
    } = _ref;
    extendParams({
      virtual: {
        enabled: false,
        slides: [],
        cache: true,
        renderSlide: null,
        renderExternal: null,
        renderExternalUpdate: true,
        addSlidesBefore: 0,
        addSlidesAfter: 0
      }
    });
    let cssModeTimeout;
    const document = getDocument();
    spswiper.virtual = {
      cache: {},
      from: undefined,
      to: undefined,
      slides: [],
      offset: 0,
      slidesGrid: []
    };
    const tempDOM = document.createElement('div');
    function renderSlide(slide, index) {
      const params = spswiper.params.virtual;
      if (params.cache && spswiper.virtual.cache[index]) {
        return spswiper.virtual.cache[index];
      }
      // eslint-disable-next-line
      let slideEl;
      if (params.renderSlide) {
        slideEl = params.renderSlide.call(spswiper, slide, index);
        if (typeof slideEl === 'string') {
          tempDOM.innerHTML = slideEl;
          slideEl = tempDOM.children[0];
        }
      } else if (spswiper.isElement) {
        slideEl = createElement('spswiper-slide');
      } else {
        slideEl = createElement('div', spswiper.params.slideClass);
      }
      slideEl.setAttribute('data-spswiper-slide-index', index);
      if (!params.renderSlide) {
        slideEl.innerHTML = slide;
      }
      if (params.cache) {
        spswiper.virtual.cache[index] = slideEl;
      }
      return slideEl;
    }
    function update(force) {
      const {
        slidesPerView,
        slidesPerGroup,
        centeredSlides,
        loop: isLoop
      } = spswiper.params;
      const {
        addSlidesBefore,
        addSlidesAfter
      } = spswiper.params.virtual;
      const {
        from: previousFrom,
        to: previousTo,
        slides,
        slidesGrid: previousSlidesGrid,
        offset: previousOffset
      } = spswiper.virtual;
      if (!spswiper.params.cssMode) {
        spswiper.updateActiveIndex();
      }
      const activeIndex = spswiper.activeIndex || 0;
      let offsetProp;
      if (spswiper.rtlTranslate) offsetProp = 'right';else offsetProp = spswiper.isHorizontal() ? 'left' : 'top';
      let slidesAfter;
      let slidesBefore;
      if (centeredSlides) {
        slidesAfter = Math.floor(slidesPerView / 2) + slidesPerGroup + addSlidesAfter;
        slidesBefore = Math.floor(slidesPerView / 2) + slidesPerGroup + addSlidesBefore;
      } else {
        slidesAfter = slidesPerView + (slidesPerGroup - 1) + addSlidesAfter;
        slidesBefore = (isLoop ? slidesPerView : slidesPerGroup) + addSlidesBefore;
      }
      let from = activeIndex - slidesBefore;
      let to = activeIndex + slidesAfter;
      if (!isLoop) {
        from = Math.max(from, 0);
        to = Math.min(to, slides.length - 1);
      }
      let offset = (spswiper.slidesGrid[from] || 0) - (spswiper.slidesGrid[0] || 0);
      if (isLoop && activeIndex >= slidesBefore) {
        from -= slidesBefore;
        if (!centeredSlides) offset += spswiper.slidesGrid[0];
      } else if (isLoop && activeIndex < slidesBefore) {
        from = -slidesBefore;
        if (centeredSlides) offset += spswiper.slidesGrid[0];
      }
      Object.assign(spswiper.virtual, {
        from,
        to,
        offset,
        slidesGrid: spswiper.slidesGrid,
        slidesBefore,
        slidesAfter
      });
      function onRendered() {
        spswiper.updateSlides();
        spswiper.updateProgress();
        spswiper.updateSlidesClasses();
        emit('virtualUpdate');
      }
      if (previousFrom === from && previousTo === to && !force) {
        if (spswiper.slidesGrid !== previousSlidesGrid && offset !== previousOffset) {
          spswiper.slides.forEach(slideEl => {
            slideEl.style[offsetProp] = `${offset - Math.abs(spswiper.cssOverflowAdjustment())}px`;
          });
        }
        spswiper.updateProgress();
        emit('virtualUpdate');
        return;
      }
      if (spswiper.params.virtual.renderExternal) {
        spswiper.params.virtual.renderExternal.call(spswiper, {
          offset,
          from,
          to,
          slides: function getSlides() {
            const slidesToRender = [];
            for (let i = from; i <= to; i += 1) {
              slidesToRender.push(slides[i]);
            }
            return slidesToRender;
          }()
        });
        if (spswiper.params.virtual.renderExternalUpdate) {
          onRendered();
        } else {
          emit('virtualUpdate');
        }
        return;
      }
      const prependIndexes = [];
      const appendIndexes = [];
      const getSlideIndex = index => {
        let slideIndex = index;
        if (index < 0) {
          slideIndex = slides.length + index;
        } else if (slideIndex >= slides.length) {
          // eslint-disable-next-line
          slideIndex = slideIndex - slides.length;
        }
        return slideIndex;
      };
      if (force) {
        spswiper.slides.filter(el => el.matches(`.${spswiper.params.slideClass}, spswiper-slide`)).forEach(slideEl => {
          slideEl.remove();
        });
      } else {
        for (let i = previousFrom; i <= previousTo; i += 1) {
          if (i < from || i > to) {
            const slideIndex = getSlideIndex(i);
            spswiper.slides.filter(el => el.matches(`.${spswiper.params.slideClass}[data-spswiper-slide-index="${slideIndex}"], spswiper-slide[data-spswiper-slide-index="${slideIndex}"]`)).forEach(slideEl => {
              slideEl.remove();
            });
          }
        }
      }
      const loopFrom = isLoop ? -slides.length : 0;
      const loopTo = isLoop ? slides.length * 2 : slides.length;
      for (let i = loopFrom; i < loopTo; i += 1) {
        if (i >= from && i <= to) {
          const slideIndex = getSlideIndex(i);
          if (typeof previousTo === 'undefined' || force) {
            appendIndexes.push(slideIndex);
          } else {
            if (i > previousTo) appendIndexes.push(slideIndex);
            if (i < previousFrom) prependIndexes.push(slideIndex);
          }
        }
      }
      appendIndexes.forEach(index => {
        spswiper.slidesEl.append(renderSlide(slides[index], index));
      });
      if (isLoop) {
        for (let i = prependIndexes.length - 1; i >= 0; i -= 1) {
          const index = prependIndexes[i];
          spswiper.slidesEl.prepend(renderSlide(slides[index], index));
        }
      } else {
        prependIndexes.sort((a, b) => b - a);
        prependIndexes.forEach(index => {
          spswiper.slidesEl.prepend(renderSlide(slides[index], index));
        });
      }
      elementChildren(spswiper.slidesEl, '.spswiper-slide, spswiper-slide').forEach(slideEl => {
        slideEl.style[offsetProp] = `${offset - Math.abs(spswiper.cssOverflowAdjustment())}px`;
      });
      onRendered();
    }
    function appendSlide(slides) {
      if (typeof slides === 'object' && 'length' in slides) {
        for (let i = 0; i < slides.length; i += 1) {
          if (slides[i]) spswiper.virtual.slides.push(slides[i]);
        }
      } else {
        spswiper.virtual.slides.push(slides);
      }
      update(true);
    }
    function prependSlide(slides) {
      const activeIndex = spswiper.activeIndex;
      let newActiveIndex = activeIndex + 1;
      let numberOfNewSlides = 1;
      if (Array.isArray(slides)) {
        for (let i = 0; i < slides.length; i += 1) {
          if (slides[i]) spswiper.virtual.slides.unshift(slides[i]);
        }
        newActiveIndex = activeIndex + slides.length;
        numberOfNewSlides = slides.length;
      } else {
        spswiper.virtual.slides.unshift(slides);
      }
      if (spswiper.params.virtual.cache) {
        const cache = spswiper.virtual.cache;
        const newCache = {};
        Object.keys(cache).forEach(cachedIndex => {
          const cachedEl = cache[cachedIndex];
          const cachedElIndex = cachedEl.getAttribute('data-spswiper-slide-index');
          if (cachedElIndex) {
            cachedEl.setAttribute('data-spswiper-slide-index', parseInt(cachedElIndex, 10) + numberOfNewSlides);
          }
          newCache[parseInt(cachedIndex, 10) + numberOfNewSlides] = cachedEl;
        });
        spswiper.virtual.cache = newCache;
      }
      update(true);
      spswiper.slideTo(newActiveIndex, 0);
    }
    function removeSlide(slidesIndexes) {
      if (typeof slidesIndexes === 'undefined' || slidesIndexes === null) return;
      let activeIndex = spswiper.activeIndex;
      if (Array.isArray(slidesIndexes)) {
        for (let i = slidesIndexes.length - 1; i >= 0; i -= 1) {
          if (spswiper.params.virtual.cache) {
            delete spswiper.virtual.cache[slidesIndexes[i]];
            // shift cache indexes
            Object.keys(spswiper.virtual.cache).forEach(key => {
              if (key > slidesIndexes) {
                spswiper.virtual.cache[key - 1] = spswiper.virtual.cache[key];
                spswiper.virtual.cache[key - 1].setAttribute('data-spswiper-slide-index', key - 1);
                delete spswiper.virtual.cache[key];
              }
            });
          }
          spswiper.virtual.slides.splice(slidesIndexes[i], 1);
          if (slidesIndexes[i] < activeIndex) activeIndex -= 1;
          activeIndex = Math.max(activeIndex, 0);
        }
      } else {
        if (spswiper.params.virtual.cache) {
          delete spswiper.virtual.cache[slidesIndexes];
          // shift cache indexes
          Object.keys(spswiper.virtual.cache).forEach(key => {
            if (key > slidesIndexes) {
              spswiper.virtual.cache[key - 1] = spswiper.virtual.cache[key];
              spswiper.virtual.cache[key - 1].setAttribute('data-spswiper-slide-index', key - 1);
              delete spswiper.virtual.cache[key];
            }
          });
        }
        spswiper.virtual.slides.splice(slidesIndexes, 1);
        if (slidesIndexes < activeIndex) activeIndex -= 1;
        activeIndex = Math.max(activeIndex, 0);
      }
      update(true);
      spswiper.slideTo(activeIndex, 0);
    }
    function removeAllSlides() {
      spswiper.virtual.slides = [];
      if (spswiper.params.virtual.cache) {
        spswiper.virtual.cache = {};
      }
      update(true);
      spswiper.slideTo(0, 0);
    }
    on('beforeInit', () => {
      if (!spswiper.params.virtual.enabled) return;
      let domSlidesAssigned;
      if (typeof spswiper.passedParams.virtual.slides === 'undefined') {
        const slides = [...spswiper.slidesEl.children].filter(el => el.matches(`.${spswiper.params.slideClass}, spswiper-slide`));
        if (slides && slides.length) {
          spswiper.virtual.slides = [...slides];
          domSlidesAssigned = true;
          slides.forEach((slideEl, slideIndex) => {
            slideEl.setAttribute('data-spswiper-slide-index', slideIndex);
            spswiper.virtual.cache[slideIndex] = slideEl;
            slideEl.remove();
          });
        }
      }
      if (!domSlidesAssigned) {
        spswiper.virtual.slides = spswiper.params.virtual.slides;
      }
      spswiper.classNames.push(`${spswiper.params.containerModifierClass}virtual`);
      spswiper.params.watchSlidesProgress = true;
      spswiper.originalParams.watchSlidesProgress = true;
      update();
    });
    on('setTranslate', () => {
      if (!spswiper.params.virtual.enabled) return;
      if (spswiper.params.cssMode && !spswiper._immediateVirtual) {
        clearTimeout(cssModeTimeout);
        cssModeTimeout = setTimeout(() => {
          update();
        }, 100);
      } else {
        update();
      }
    });
    on('init update resize', () => {
      if (!spswiper.params.virtual.enabled) return;
      if (spswiper.params.cssMode) {
        setCSSProperty(spswiper.wrapperEl, '--spswiper-virtual-size', `${spswiper.virtualSize}px`);
      }
    });
    Object.assign(spswiper.virtual, {
      appendSlide,
      prependSlide,
      removeSlide,
      removeAllSlides,
      update
    });
  }

  /* eslint-disable consistent-return */
  function Keyboard(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit
    } = _ref;
    const document = getDocument();
    const window = getWindow();
    spswiper.keyboard = {
      enabled: false
    };
    extendParams({
      keyboard: {
        enabled: false,
        onlyInViewport: true,
        pageUpDown: true
      }
    });
    function handle(event) {
      if (!spswiper.enabled) return;
      const {
        rtlTranslate: rtl
      } = spswiper;
      let e = event;
      if (e.originalEvent) e = e.originalEvent; // jquery fix
      const kc = e.keyCode || e.charCode;
      const pageUpDown = spswiper.params.keyboard.pageUpDown;
      const isPageUp = pageUpDown && kc === 33;
      const isPageDown = pageUpDown && kc === 34;
      const isArrowLeft = kc === 37;
      const isArrowRight = kc === 39;
      const isArrowUp = kc === 38;
      const isArrowDown = kc === 40;
      // Directions locks
      if (!spswiper.allowSlideNext && (spswiper.isHorizontal() && isArrowRight || spswiper.isVertical() && isArrowDown || isPageDown)) {
        return false;
      }
      if (!spswiper.allowSlidePrev && (spswiper.isHorizontal() && isArrowLeft || spswiper.isVertical() && isArrowUp || isPageUp)) {
        return false;
      }
      if (e.shiftKey || e.altKey || e.ctrlKey || e.metaKey) {
        return undefined;
      }
      if (document.activeElement && document.activeElement.nodeName && (document.activeElement.nodeName.toLowerCase() === 'input' || document.activeElement.nodeName.toLowerCase() === 'textarea')) {
        return undefined;
      }
      if (spswiper.params.keyboard.onlyInViewport && (isPageUp || isPageDown || isArrowLeft || isArrowRight || isArrowUp || isArrowDown)) {
        let inView = false;
        // Check that spswiper should be inside of visible area of window
        if (elementParents(spswiper.el, `.${spswiper.params.slideClass}, spswiper-slide`).length > 0 && elementParents(spswiper.el, `.${spswiper.params.slideActiveClass}`).length === 0) {
          return undefined;
        }
        const el = spswiper.el;
        const spswiperWidth = el.clientWidth;
        const spswiperHeight = el.clientHeight;
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;
        const spswiperOffset = elementOffset(el);
        if (rtl) spswiperOffset.left -= el.scrollLeft;
        const spswiperCoord = [[spswiperOffset.left, spswiperOffset.top], [spswiperOffset.left + spswiperWidth, spswiperOffset.top], [spswiperOffset.left, spswiperOffset.top + spswiperHeight], [spswiperOffset.left + spswiperWidth, spswiperOffset.top + spswiperHeight]];
        for (let i = 0; i < spswiperCoord.length; i += 1) {
          const point = spswiperCoord[i];
          if (point[0] >= 0 && point[0] <= windowWidth && point[1] >= 0 && point[1] <= windowHeight) {
            if (point[0] === 0 && point[1] === 0) continue; // eslint-disable-line
            inView = true;
          }
        }
        if (!inView) return undefined;
      }
      if (spswiper.isHorizontal()) {
        if (isPageUp || isPageDown || isArrowLeft || isArrowRight) {
          if (e.preventDefault) e.preventDefault();else e.returnValue = false;
        }
        if ((isPageDown || isArrowRight) && !rtl || (isPageUp || isArrowLeft) && rtl) spswiper.slideNext();
        if ((isPageUp || isArrowLeft) && !rtl || (isPageDown || isArrowRight) && rtl) spswiper.slidePrev();
      } else {
        if (isPageUp || isPageDown || isArrowUp || isArrowDown) {
          if (e.preventDefault) e.preventDefault();else e.returnValue = false;
        }
        if (isPageDown || isArrowDown) spswiper.slideNext();
        if (isPageUp || isArrowUp) spswiper.slidePrev();
      }
      emit('keyPress', kc);
      return undefined;
    }
    function enable() {
      if (spswiper.keyboard.enabled) return;
      document.addEventListener('keydown', handle);
      spswiper.keyboard.enabled = true;
    }
    function disable() {
      if (!spswiper.keyboard.enabled) return;
      document.removeEventListener('keydown', handle);
      spswiper.keyboard.enabled = false;
    }
    on('init', () => {
      if (spswiper.params.keyboard.enabled) {
        enable();
      }
    });
    on('destroy', () => {
      if (spswiper.keyboard.enabled) {
        disable();
      }
    });
    Object.assign(spswiper.keyboard, {
      enable,
      disable
    });
  }

  /* eslint-disable consistent-return */
  function Mousewheel(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit
    } = _ref;
    const window = getWindow();
    extendParams({
      mousewheel: {
        enabled: false,
        releaseOnEdges: false,
        invert: false,
        forceToAxis: false,
        sensitivity: 1,
        eventsTarget: 'container',
        thresholdDelta: null,
        thresholdTime: null,
        noMousewheelClass: 'spswiper-no-mousewheel'
      }
    });
    spswiper.mousewheel = {
      enabled: false
    };
    let timeout;
    let lastScrollTime = now();
    let lastEventBeforeSnap;
    const recentWheelEvents = [];
    function normalize(e) {
      // Reasonable defaults
      const PIXEL_STEP = 10;
      const LINE_HEIGHT = 40;
      const PAGE_HEIGHT = 800;
      let sX = 0;
      let sY = 0; // spinX, spinY
      let pX = 0;
      let pY = 0; // pixelX, pixelY

      // Legacy
      if ('detail' in e) {
        sY = e.detail;
      }
      if ('wheelDelta' in e) {
        sY = -e.wheelDelta / 120;
      }
      if ('wheelDeltaY' in e) {
        sY = -e.wheelDeltaY / 120;
      }
      if ('wheelDeltaX' in e) {
        sX = -e.wheelDeltaX / 120;
      }

      // side scrolling on FF with DOMMouseScroll
      if ('axis' in e && e.axis === e.HORIZONTAL_AXIS) {
        sX = sY;
        sY = 0;
      }
      pX = sX * PIXEL_STEP;
      pY = sY * PIXEL_STEP;
      if ('deltaY' in e) {
        pY = e.deltaY;
      }
      if ('deltaX' in e) {
        pX = e.deltaX;
      }
      if (e.shiftKey && !pX) {
        // if user scrolls with shift he wants horizontal scroll
        pX = pY;
        pY = 0;
      }
      if ((pX || pY) && e.deltaMode) {
        if (e.deltaMode === 1) {
          // delta in LINE units
          pX *= LINE_HEIGHT;
          pY *= LINE_HEIGHT;
        } else {
          // delta in PAGE units
          pX *= PAGE_HEIGHT;
          pY *= PAGE_HEIGHT;
        }
      }

      // Fall-back if spin cannot be determined
      if (pX && !sX) {
        sX = pX < 1 ? -1 : 1;
      }
      if (pY && !sY) {
        sY = pY < 1 ? -1 : 1;
      }
      return {
        spinX: sX,
        spinY: sY,
        pixelX: pX,
        pixelY: pY
      };
    }
    function handleMouseEnter() {
      if (!spswiper.enabled) return;
      spswiper.mouseEntered = true;
    }
    function handleMouseLeave() {
      if (!spswiper.enabled) return;
      spswiper.mouseEntered = false;
    }
    function animateSlider(newEvent) {
      if (spswiper.params.mousewheel.thresholdDelta && newEvent.delta < spswiper.params.mousewheel.thresholdDelta) {
        // Prevent if delta of wheel scroll delta is below configured threshold
        return false;
      }
      if (spswiper.params.mousewheel.thresholdTime && now() - lastScrollTime < spswiper.params.mousewheel.thresholdTime) {
        // Prevent if time between scrolls is below configured threshold
        return false;
      }

      // If the movement is NOT big enough and
      // if the last time the user scrolled was too close to the current one (avoid continuously triggering the slider):
      //   Don't go any further (avoid insignificant scroll movement).
      if (newEvent.delta >= 6 && now() - lastScrollTime < 60) {
        // Return false as a default
        return true;
      }
      // If user is scrolling towards the end:
      //   If the slider hasn't hit the latest slide or
      //   if the slider is a loop and
      //   if the slider isn't moving right now:
      //     Go to next slide and
      //     emit a scroll event.
      // Else (the user is scrolling towards the beginning) and
      // if the slider hasn't hit the first slide or
      // if the slider is a loop and
      // if the slider isn't moving right now:
      //   Go to prev slide and
      //   emit a scroll event.
      if (newEvent.direction < 0) {
        if ((!spswiper.isEnd || spswiper.params.loop) && !spswiper.animating) {
          spswiper.slideNext();
          emit('scroll', newEvent.raw);
        }
      } else if ((!spswiper.isBeginning || spswiper.params.loop) && !spswiper.animating) {
        spswiper.slidePrev();
        emit('scroll', newEvent.raw);
      }
      // If you got here is because an animation has been triggered so store the current time
      lastScrollTime = new window.Date().getTime();
      // Return false as a default
      return false;
    }
    function releaseScroll(newEvent) {
      const params = spswiper.params.mousewheel;
      if (newEvent.direction < 0) {
        if (spswiper.isEnd && !spswiper.params.loop && params.releaseOnEdges) {
          // Return true to animate scroll on edges
          return true;
        }
      } else if (spswiper.isBeginning && !spswiper.params.loop && params.releaseOnEdges) {
        // Return true to animate scroll on edges
        return true;
      }
      return false;
    }
    function handle(event) {
      let e = event;
      let disableParentSPSwiper = true;
      if (!spswiper.enabled) return;

      // Ignore event if the target or its parents have the spswiper-no-mousewheel class
      if (event.target.closest(`.${spswiper.params.mousewheel.noMousewheelClass}`)) return;
      const params = spswiper.params.mousewheel;
      if (spswiper.params.cssMode) {
        e.preventDefault();
      }
      let targetEl = spswiper.el;
      if (spswiper.params.mousewheel.eventsTarget !== 'container') {
        targetEl = document.querySelector(spswiper.params.mousewheel.eventsTarget);
      }
      const targetElContainsTarget = targetEl && targetEl.contains(e.target);
      if (!spswiper.mouseEntered && !targetElContainsTarget && !params.releaseOnEdges) return true;
      if (e.originalEvent) e = e.originalEvent; // jquery fix
      let delta = 0;
      const rtlFactor = spswiper.rtlTranslate ? -1 : 1;
      const data = normalize(e);
      if (params.forceToAxis) {
        if (spswiper.isHorizontal()) {
          if (Math.abs(data.pixelX) > Math.abs(data.pixelY)) delta = -data.pixelX * rtlFactor;else return true;
        } else if (Math.abs(data.pixelY) > Math.abs(data.pixelX)) delta = -data.pixelY;else return true;
      } else {
        delta = Math.abs(data.pixelX) > Math.abs(data.pixelY) ? -data.pixelX * rtlFactor : -data.pixelY;
      }
      if (delta === 0) return true;
      if (params.invert) delta = -delta;

      // Get the scroll positions
      let positions = spswiper.getTranslate() + delta * params.sensitivity;
      if (positions >= spswiper.minTranslate()) positions = spswiper.minTranslate();
      if (positions <= spswiper.maxTranslate()) positions = spswiper.maxTranslate();

      // When loop is true:
      //     the disableParentSPSwiper will be true.
      // When loop is false:
      //     if the scroll positions is not on edge,
      //     then the disableParentSPSwiper will be true.
      //     if the scroll on edge positions,
      //     then the disableParentSPSwiper will be false.
      disableParentSPSwiper = spswiper.params.loop ? true : !(positions === spswiper.minTranslate() || positions === spswiper.maxTranslate());
      if (disableParentSPSwiper && spswiper.params.nested) e.stopPropagation();
      if (!spswiper.params.freeMode || !spswiper.params.freeMode.enabled) {
        // Register the new event in a variable which stores the relevant data
        const newEvent = {
          time: now(),
          delta: Math.abs(delta),
          direction: Math.sign(delta),
          raw: event
        };

        // Keep the most recent events
        if (recentWheelEvents.length >= 2) {
          recentWheelEvents.shift(); // only store the last N events
        }

        const prevEvent = recentWheelEvents.length ? recentWheelEvents[recentWheelEvents.length - 1] : undefined;
        recentWheelEvents.push(newEvent);

        // If there is at least one previous recorded event:
        //   If direction has changed or
        //   if the scroll is quicker than the previous one:
        //     Animate the slider.
        // Else (this is the first time the wheel is moved):
        //     Animate the slider.
        if (prevEvent) {
          if (newEvent.direction !== prevEvent.direction || newEvent.delta > prevEvent.delta || newEvent.time > prevEvent.time + 150) {
            animateSlider(newEvent);
          }
        } else {
          animateSlider(newEvent);
        }

        // If it's time to release the scroll:
        //   Return now so you don't hit the preventDefault.
        if (releaseScroll(newEvent)) {
          return true;
        }
      } else {
        // Freemode or scrollContainer:

        // If we recently snapped after a momentum scroll, then ignore wheel events
        // to give time for the deceleration to finish. Stop ignoring after 500 msecs
        // or if it's a new scroll (larger delta or inverse sign as last event before
        // an end-of-momentum snap).
        const newEvent = {
          time: now(),
          delta: Math.abs(delta),
          direction: Math.sign(delta)
        };
        const ignoreWheelEvents = lastEventBeforeSnap && newEvent.time < lastEventBeforeSnap.time + 500 && newEvent.delta <= lastEventBeforeSnap.delta && newEvent.direction === lastEventBeforeSnap.direction;
        if (!ignoreWheelEvents) {
          lastEventBeforeSnap = undefined;
          let position = spswiper.getTranslate() + delta * params.sensitivity;
          const wasBeginning = spswiper.isBeginning;
          const wasEnd = spswiper.isEnd;
          if (position >= spswiper.minTranslate()) position = spswiper.minTranslate();
          if (position <= spswiper.maxTranslate()) position = spswiper.maxTranslate();
          spswiper.setTransition(0);
          spswiper.setTranslate(position);
          spswiper.updateProgress();
          spswiper.updateActiveIndex();
          spswiper.updateSlidesClasses();
          if (!wasBeginning && spswiper.isBeginning || !wasEnd && spswiper.isEnd) {
            spswiper.updateSlidesClasses();
          }
          if (spswiper.params.loop) {
            spswiper.loopFix({
              direction: newEvent.direction < 0 ? 'next' : 'prev',
              byMousewheel: true
            });
          }
          if (spswiper.params.freeMode.sticky) {
            // When wheel scrolling starts with sticky (aka snap) enabled, then detect
            // the end of a momentum scroll by storing recent (N=15?) wheel events.
            // 1. do all N events have decreasing or same (absolute value) delta?
            // 2. did all N events arrive in the last M (M=500?) msecs?
            // 3. does the earliest event have an (absolute value) delta that's
            //    at least P (P=1?) larger than the most recent event's delta?
            // 4. does the latest event have a delta that's smaller than Q (Q=6?) pixels?
            // If 1-4 are "yes" then we're near the end of a momentum scroll deceleration.
            // Snap immediately and ignore remaining wheel events in this scroll.
            // See comment above for "remaining wheel events in this scroll" determination.
            // If 1-4 aren't satisfied, then wait to snap until 500ms after the last event.
            clearTimeout(timeout);
            timeout = undefined;
            if (recentWheelEvents.length >= 15) {
              recentWheelEvents.shift(); // only store the last N events
            }

            const prevEvent = recentWheelEvents.length ? recentWheelEvents[recentWheelEvents.length - 1] : undefined;
            const firstEvent = recentWheelEvents[0];
            recentWheelEvents.push(newEvent);
            if (prevEvent && (newEvent.delta > prevEvent.delta || newEvent.direction !== prevEvent.direction)) {
              // Increasing or reverse-sign delta means the user started scrolling again. Clear the wheel event log.
              recentWheelEvents.splice(0);
            } else if (recentWheelEvents.length >= 15 && newEvent.time - firstEvent.time < 500 && firstEvent.delta - newEvent.delta >= 1 && newEvent.delta <= 6) {
              // We're at the end of the deceleration of a momentum scroll, so there's no need
              // to wait for more events. Snap ASAP on the next tick.
              // Also, because there's some remaining momentum we'll bias the snap in the
              // direction of the ongoing scroll because it's better UX for the scroll to snap
              // in the same direction as the scroll instead of reversing to snap.  Therefore,
              // if it's already scrolled more than 20% in the current direction, keep going.
              const snapToThreshold = delta > 0 ? 0.8 : 0.2;
              lastEventBeforeSnap = newEvent;
              recentWheelEvents.splice(0);
              timeout = nextTick(() => {
                spswiper.slideToClosest(spswiper.params.speed, true, undefined, snapToThreshold);
              }, 0); // no delay; move on next tick
            }

            if (!timeout) {
              // if we get here, then we haven't detected the end of a momentum scroll, so
              // we'll consider a scroll "complete" when there haven't been any wheel events
              // for 500ms.
              timeout = nextTick(() => {
                const snapToThreshold = 0.5;
                lastEventBeforeSnap = newEvent;
                recentWheelEvents.splice(0);
                spswiper.slideToClosest(spswiper.params.speed, true, undefined, snapToThreshold);
              }, 500);
            }
          }

          // Emit event
          if (!ignoreWheelEvents) emit('scroll', e);

          // Stop autoplay
          if (spswiper.params.autoplay && spswiper.params.autoplayDisableOnInteraction) spswiper.autoplay.stop();
          // Return page scroll on edge positions
          if (params.releaseOnEdges && (position === spswiper.minTranslate() || position === spswiper.maxTranslate())) {
            return true;
          }
        }
      }
      if (e.preventDefault) e.preventDefault();else e.returnValue = false;
      return false;
    }
    function events(method) {
      let targetEl = spswiper.el;
      if (spswiper.params.mousewheel.eventsTarget !== 'container') {
        targetEl = document.querySelector(spswiper.params.mousewheel.eventsTarget);
      }
      targetEl[method]('mouseenter', handleMouseEnter);
      targetEl[method]('mouseleave', handleMouseLeave);
      targetEl[method]('wheel', handle);
    }
    function enable() {
      if (spswiper.params.cssMode) {
        spswiper.wrapperEl.removeEventListener('wheel', handle);
        return true;
      }
      if (spswiper.mousewheel.enabled) return false;
      events('addEventListener');
      spswiper.mousewheel.enabled = true;
      return true;
    }
    function disable() {
      if (spswiper.params.cssMode) {
        spswiper.wrapperEl.addEventListener(event, handle);
        return true;
      }
      if (!spswiper.mousewheel.enabled) return false;
      events('removeEventListener');
      spswiper.mousewheel.enabled = false;
      return true;
    }
    on('init', () => {
      if (!spswiper.params.mousewheel.enabled && spswiper.params.cssMode) {
        disable();
      }
      if (spswiper.params.mousewheel.enabled) enable();
    });
    on('destroy', () => {
      if (spswiper.params.cssMode) {
        enable();
      }
      if (spswiper.mousewheel.enabled) disable();
    });
    Object.assign(spswiper.mousewheel, {
      enable,
      disable
    });
  }

  function createElementIfNotDefined(spswiper, originalParams, params, checkProps) {
    if (spswiper.params.createElements) {
      Object.keys(checkProps).forEach(key => {
        if (!params[key] && params.auto === true) {
          let element = elementChildren(spswiper.el, `.${checkProps[key]}`)[0];
          if (!element) {
            element = createElement('div', checkProps[key]);
            element.className = checkProps[key];
            spswiper.el.append(element);
          }
          params[key] = element;
          originalParams[key] = element;
        }
      });
    }
    return params;
  }

  function Navigation(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit
    } = _ref;
    extendParams({
      navigation: {
        nextEl: null,
        prevEl: null,
        hideOnClick: false,
        disabledClass: 'spswiper-button-disabled',
        hiddenClass: 'spswiper-button-hidden',
        lockClass: 'spswiper-button-lock',
        navigationDisabledClass: 'spswiper-navigation-disabled'
      }
    });
    spswiper.navigation = {
      nextEl: null,
      prevEl: null
    };
    const makeElementsArray = el => (Array.isArray(el) ? el : [el]).filter(e => !!e);
    function getEl(el) {
      let res;
      if (el && typeof el === 'string' && spswiper.isElement) {
        res = spswiper.el.querySelector(el);
        if (res) return res;
      }
      if (el) {
        if (typeof el === 'string') res = [...document.querySelectorAll(el)];
        if (spswiper.params.uniqueNavElements && typeof el === 'string' && res.length > 1 && spswiper.el.querySelectorAll(el).length === 1) {
          res = spswiper.el.querySelector(el);
        }
      }
      if (el && !res) return el;
      // if (Array.isArray(res) && res.length === 1) res = res[0];
      return res;
    }
    function toggleEl(el, disabled) {
      const params = spswiper.params.navigation;
      el = makeElementsArray(el);
      el.forEach(subEl => {
        if (subEl) {
          subEl.classList[disabled ? 'add' : 'remove'](...params.disabledClass.split(' '));
          if (subEl.tagName === 'BUTTON') subEl.disabled = disabled;
          if (spswiper.params.watchOverflow && spswiper.enabled) {
            subEl.classList[spswiper.isLocked ? 'add' : 'remove'](params.lockClass);
          }
        }
      });
    }
    function update() {
      // Update Navigation Buttons
      const {
        nextEl,
        prevEl
      } = spswiper.navigation;
      if (spswiper.params.loop) {
        toggleEl(prevEl, false);
        toggleEl(nextEl, false);
        return;
      }
      toggleEl(prevEl, spswiper.isBeginning && !spswiper.params.rewind);
      toggleEl(nextEl, spswiper.isEnd && !spswiper.params.rewind);
    }
    function onPrevClick(e) {
      e.preventDefault();
      if (spswiper.isBeginning && !spswiper.params.loop && !spswiper.params.rewind) return;
      spswiper.slidePrev();
      emit('navigationPrev');
    }
    function onNextClick(e) {
      e.preventDefault();
      if (spswiper.isEnd && !spswiper.params.loop && !spswiper.params.rewind) return;
      spswiper.slideNext();
      emit('navigationNext');
    }
    function init() {
      const params = spswiper.params.navigation;
      spswiper.params.navigation = createElementIfNotDefined(spswiper, spswiper.originalParams.navigation, spswiper.params.navigation, {
        nextEl: 'spswiper-button-next',
        prevEl: 'spswiper-button-prev'
      });
      if (!(params.nextEl || params.prevEl)) return;
      let nextEl = getEl(params.nextEl);
      let prevEl = getEl(params.prevEl);
      Object.assign(spswiper.navigation, {
        nextEl,
        prevEl
      });
      nextEl = makeElementsArray(nextEl);
      prevEl = makeElementsArray(prevEl);
      const initButton = (el, dir) => {
        if (el) {
          el.addEventListener('click', dir === 'next' ? onNextClick : onPrevClick);
        }
        if (!spswiper.enabled && el) {
          el.classList.add(...params.lockClass.split(' '));
        }
      };
      nextEl.forEach(el => initButton(el, 'next'));
      prevEl.forEach(el => initButton(el, 'prev'));
    }
    function destroy() {
      let {
        nextEl,
        prevEl
      } = spswiper.navigation;
      nextEl = makeElementsArray(nextEl);
      prevEl = makeElementsArray(prevEl);
      const destroyButton = (el, dir) => {
        el.removeEventListener('click', dir === 'next' ? onNextClick : onPrevClick);
        el.classList.remove(...spswiper.params.navigation.disabledClass.split(' '));
      };
      nextEl.forEach(el => destroyButton(el, 'next'));
      prevEl.forEach(el => destroyButton(el, 'prev'));
    }
    on('init', () => {
      if (spswiper.params.navigation.enabled === false) {
        // eslint-disable-next-line
        disable();
      } else {
        init();
        update();
      }
    });
    on('toEdge fromEdge lock unlock', () => {
      update();
    });
    on('destroy', () => {
      destroy();
    });
    on('enable disable', () => {
      let {
        nextEl,
        prevEl
      } = spswiper.navigation;
      nextEl = makeElementsArray(nextEl);
      prevEl = makeElementsArray(prevEl);
      if (spswiper.enabled) {
        update();
        return;
      }
      [...nextEl, ...prevEl].filter(el => !!el).forEach(el => el.classList.add(spswiper.params.navigation.lockClass));
    });
    on('click', (_s, e) => {
      let {
        nextEl,
        prevEl
      } = spswiper.navigation;
      nextEl = makeElementsArray(nextEl);
      prevEl = makeElementsArray(prevEl);
      const targetEl = e.target;
      if (spswiper.params.navigation.hideOnClick && !prevEl.includes(targetEl) && !nextEl.includes(targetEl)) {
        if (spswiper.pagination && spswiper.params.pagination && spswiper.params.pagination.clickable && (spswiper.pagination.el === targetEl || spswiper.pagination.el.contains(targetEl))) return;
        let isHidden;
        if (nextEl.length) {
          isHidden = nextEl[0].classList.contains(spswiper.params.navigation.hiddenClass);
        } else if (prevEl.length) {
          isHidden = prevEl[0].classList.contains(spswiper.params.navigation.hiddenClass);
        }
        if (isHidden === true) {
          emit('navigationShow');
        } else {
          emit('navigationHide');
        }
        [...nextEl, ...prevEl].filter(el => !!el).forEach(el => el.classList.toggle(spswiper.params.navigation.hiddenClass));
      }
    });
    const enable = () => {
      spswiper.el.classList.remove(...spswiper.params.navigation.navigationDisabledClass.split(' '));
      init();
      update();
    };
    const disable = () => {
      spswiper.el.classList.add(...spswiper.params.navigation.navigationDisabledClass.split(' '));
      destroy();
    };
    Object.assign(spswiper.navigation, {
      enable,
      disable,
      update,
      init,
      destroy
    });
  }

  function classesToSelector(classes) {
    if (classes === void 0) {
      classes = '';
    }
    return `.${classes.trim().replace(/([\.:!+\/])/g, '\\$1') // eslint-disable-line
  .replace(/ /g, '.')}`;
  }

  function Pagination(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit
    } = _ref;
    const pfx = 'spswiper-pagination';
    extendParams({
      pagination: {
        el: null,
        bulletElement: 'span',
        clickable: false,
        hideOnClick: false,
        renderBullet: null,
        renderProgressbar: null,
        renderFraction: null,
        renderCustom: null,
        progressbarOpposite: false,
        type: 'bullets',
        // 'bullets' or 'progressbar' or 'fraction' or 'custom'
        dynamicBullets: false,
        dynamicMainBullets: 1,
        formatFractionCurrent: number => number,
        formatFractionTotal: number => number,
        bulletClass: `${pfx}-bullet`,
        bulletActiveClass: `${pfx}-bullet-active`,
        modifierClass: `${pfx}-`,
        currentClass: `${pfx}-current`,
        totalClass: `${pfx}-total`,
        hiddenClass: `${pfx}-hidden`,
        progressbarFillClass: `${pfx}-progressbar-fill`,
        progressbarOppositeClass: `${pfx}-progressbar-opposite`,
        clickableClass: `${pfx}-clickable`,
        lockClass: `${pfx}-lock`,
        horizontalClass: `${pfx}-horizontal`,
        verticalClass: `${pfx}-vertical`,
        paginationDisabledClass: `${pfx}-disabled`
      }
    });
    spswiper.pagination = {
      el: null,
      bullets: []
    };
    let bulletSize;
    let dynamicBulletIndex = 0;
    const makeElementsArray = el => (Array.isArray(el) ? el : [el]).filter(e => !!e);
    function isPaginationDisabled() {
      return !spswiper.params.pagination.el || !spswiper.pagination.el || Array.isArray(spswiper.pagination.el) && spswiper.pagination.el.length === 0;
    }
    function setSideBullets(bulletEl, position) {
      const {
        bulletActiveClass
      } = spswiper.params.pagination;
      if (!bulletEl) return;
      bulletEl = bulletEl[`${position === 'prev' ? 'previous' : 'next'}ElementSibling`];
      if (bulletEl) {
        bulletEl.classList.add(`${bulletActiveClass}-${position}`);
        bulletEl = bulletEl[`${position === 'prev' ? 'previous' : 'next'}ElementSibling`];
        if (bulletEl) {
          bulletEl.classList.add(`${bulletActiveClass}-${position}-${position}`);
        }
      }
    }
    function onBulletClick(e) {
      const bulletEl = e.target.closest(classesToSelector(spswiper.params.pagination.bulletClass));
      if (!bulletEl) {
        return;
      }
      e.preventDefault();
      const index = elementIndex(bulletEl) * spswiper.params.slidesPerGroup;
      if (spswiper.params.loop) {
        if (spswiper.realIndex === index) return;
        const realIndex = spswiper.realIndex;
        const newSlideIndex = spswiper.getSlideIndexByData(index);
        const currentSlideIndex = spswiper.getSlideIndexByData(spswiper.realIndex);
        const loopFix = dir => {
          const indexBeforeLoopFix = spswiper.activeIndex;
          spswiper.loopFix({
            direction: dir,
            activeSlideIndex: newSlideIndex,
            slideTo: false
          });
          const indexAfterFix = spswiper.activeIndex;
          if (indexBeforeLoopFix === indexAfterFix) {
            spswiper.slideToLoop(realIndex, 0, false, true);
          }
        };
        if (newSlideIndex > spswiper.slides.length - spswiper.loopedSlides) {
          loopFix(newSlideIndex > currentSlideIndex ? 'next' : 'prev');
        } else if (spswiper.params.centeredSlides) {
          const slidesPerView = spswiper.params.slidesPerView === 'auto' ? spswiper.slidesPerViewDynamic() : Math.ceil(parseFloat(spswiper.params.slidesPerView, 10));
          if (newSlideIndex < Math.floor(slidesPerView / 2)) {
            loopFix('prev');
          }
        }
        spswiper.slideToLoop(index);
      } else {
        spswiper.slideTo(index);
      }
    }
    function update() {
      // Render || Update Pagination bullets/items
      const rtl = spswiper.rtl;
      const params = spswiper.params.pagination;
      if (isPaginationDisabled()) return;
      let el = spswiper.pagination.el;
      el = makeElementsArray(el);
      // Current/Total
      let current;
      let previousIndex;
      const slidesLength = spswiper.virtual && spswiper.params.virtual.enabled ? spswiper.virtual.slides.length : spswiper.slides.length;
      const total = spswiper.params.loop ? Math.ceil(slidesLength / spswiper.params.slidesPerGroup) : spswiper.snapGrid.length;
      if (spswiper.params.loop) {
        previousIndex = spswiper.previousRealIndex || 0;
        current = spswiper.params.slidesPerGroup > 1 ? Math.floor(spswiper.realIndex / spswiper.params.slidesPerGroup) : spswiper.realIndex;
      } else if (typeof spswiper.snapIndex !== 'undefined') {
        current = spswiper.snapIndex;
        previousIndex = spswiper.previousSnapIndex;
      } else {
        previousIndex = spswiper.previousIndex || 0;
        current = spswiper.activeIndex || 0;
      }
      // Types
      if (params.type === 'bullets' && spswiper.pagination.bullets && spswiper.pagination.bullets.length > 0) {
        const bullets = spswiper.pagination.bullets;
        let firstIndex;
        let lastIndex;
        let midIndex;
        if (params.dynamicBullets) {
          bulletSize = elementOuterSize(bullets[0], spswiper.isHorizontal() ? 'width' : 'height', true);
          el.forEach(subEl => {
            subEl.style[spswiper.isHorizontal() ? 'width' : 'height'] = `${bulletSize * (params.dynamicMainBullets + 4)}px`;
          });
          if (params.dynamicMainBullets > 1 && previousIndex !== undefined) {
            dynamicBulletIndex += current - (previousIndex || 0);
            if (dynamicBulletIndex > params.dynamicMainBullets - 1) {
              dynamicBulletIndex = params.dynamicMainBullets - 1;
            } else if (dynamicBulletIndex < 0) {
              dynamicBulletIndex = 0;
            }
          }
          firstIndex = Math.max(current - dynamicBulletIndex, 0);
          lastIndex = firstIndex + (Math.min(bullets.length, params.dynamicMainBullets) - 1);
          midIndex = (lastIndex + firstIndex) / 2;
        }
        bullets.forEach(bulletEl => {
          const classesToRemove = [...['', '-next', '-next-next', '-prev', '-prev-prev', '-main'].map(suffix => `${params.bulletActiveClass}${suffix}`)].map(s => typeof s === 'string' && s.includes(' ') ? s.split(' ') : s).flat();
          bulletEl.classList.remove(...classesToRemove);
        });
        if (el.length > 1) {
          bullets.forEach(bullet => {
            const bulletIndex = elementIndex(bullet);
            if (bulletIndex === current) {
              bullet.classList.add(...params.bulletActiveClass.split(' '));
            } else if (spswiper.isElement) {
              bullet.setAttribute('part', 'bullet');
            }
            if (params.dynamicBullets) {
              if (bulletIndex >= firstIndex && bulletIndex <= lastIndex) {
                bullet.classList.add(...`${params.bulletActiveClass}-main`.split(' '));
              }
              if (bulletIndex === firstIndex) {
                setSideBullets(bullet, 'prev');
              }
              if (bulletIndex === lastIndex) {
                setSideBullets(bullet, 'next');
              }
            }
          });
        } else {
          const bullet = bullets[current];
          if (bullet) {
            bullet.classList.add(...params.bulletActiveClass.split(' '));
          }
          if (spswiper.isElement) {
            bullets.forEach((bulletEl, bulletIndex) => {
              bulletEl.setAttribute('part', bulletIndex === current ? 'bullet-active' : 'bullet');
            });
          }
          if (params.dynamicBullets) {
            const firstDisplayedBullet = bullets[firstIndex];
            const lastDisplayedBullet = bullets[lastIndex];
            for (let i = firstIndex; i <= lastIndex; i += 1) {
              if (bullets[i]) {
                bullets[i].classList.add(...`${params.bulletActiveClass}-main`.split(' '));
              }
            }
            setSideBullets(firstDisplayedBullet, 'prev');
            setSideBullets(lastDisplayedBullet, 'next');
          }
        }
        if (params.dynamicBullets) {
          const dynamicBulletsLength = Math.min(bullets.length, params.dynamicMainBullets + 4);
          const bulletsOffset = (bulletSize * dynamicBulletsLength - bulletSize) / 2 - midIndex * bulletSize;
          const offsetProp = rtl ? 'right' : 'left';
          bullets.forEach(bullet => {
            bullet.style[spswiper.isHorizontal() ? offsetProp : 'top'] = `${bulletsOffset}px`;
          });
        }
      }
      el.forEach((subEl, subElIndex) => {
        if (params.type === 'fraction') {
          subEl.querySelectorAll(classesToSelector(params.currentClass)).forEach(fractionEl => {
            fractionEl.textContent = params.formatFractionCurrent(current + 1);
          });
          subEl.querySelectorAll(classesToSelector(params.totalClass)).forEach(totalEl => {
            totalEl.textContent = params.formatFractionTotal(total);
          });
        }
        if (params.type === 'progressbar') {
          let progressbarDirection;
          if (params.progressbarOpposite) {
            progressbarDirection = spswiper.isHorizontal() ? 'vertical' : 'horizontal';
          } else {
            progressbarDirection = spswiper.isHorizontal() ? 'horizontal' : 'vertical';
          }
          const scale = (current + 1) / total;
          let scaleX = 1;
          let scaleY = 1;
          if (progressbarDirection === 'horizontal') {
            scaleX = scale;
          } else {
            scaleY = scale;
          }
          subEl.querySelectorAll(classesToSelector(params.progressbarFillClass)).forEach(progressEl => {
            progressEl.style.transform = `translate3d(0,0,0) scaleX(${scaleX}) scaleY(${scaleY})`;
            progressEl.style.transitionDuration = `${spswiper.params.speed}ms`;
          });
        }
        if (params.type === 'custom' && params.renderCustom) {
          subEl.innerHTML = params.renderCustom(spswiper, current + 1, total);
          if (subElIndex === 0) emit('paginationRender', subEl);
        } else {
          if (subElIndex === 0) emit('paginationRender', subEl);
          emit('paginationUpdate', subEl);
        }
        if (spswiper.params.watchOverflow && spswiper.enabled) {
          subEl.classList[spswiper.isLocked ? 'add' : 'remove'](params.lockClass);
        }
      });
    }
    function render() {
      // Render Container
      const params = spswiper.params.pagination;
      if (isPaginationDisabled()) return;
      const slidesLength = spswiper.virtual && spswiper.params.virtual.enabled ? spswiper.virtual.slides.length : spswiper.slides.length;
      let el = spswiper.pagination.el;
      el = makeElementsArray(el);
      let paginationHTML = '';
      if (params.type === 'bullets') {
        let numberOfBullets = spswiper.params.loop ? Math.ceil(slidesLength / spswiper.params.slidesPerGroup) : spswiper.snapGrid.length;
        if (spswiper.params.freeMode && spswiper.params.freeMode.enabled && numberOfBullets > slidesLength) {
          numberOfBullets = slidesLength;
        }
        for (let i = 0; i < numberOfBullets; i += 1) {
          if (params.renderBullet) {
            paginationHTML += params.renderBullet.call(spswiper, i, params.bulletClass);
          } else {
            // prettier-ignore
            paginationHTML += `<${params.bulletElement} ${spswiper.isElement ? 'part="bullet"' : ''} class="${params.bulletClass}"></${params.bulletElement}>`;
          }
        }
      }
      if (params.type === 'fraction') {
        if (params.renderFraction) {
          paginationHTML = params.renderFraction.call(spswiper, params.currentClass, params.totalClass);
        } else {
          paginationHTML = `<span class="${params.currentClass}"></span>` + ' / ' + `<span class="${params.totalClass}"></span>`;
        }
      }
      if (params.type === 'progressbar') {
        if (params.renderProgressbar) {
          paginationHTML = params.renderProgressbar.call(spswiper, params.progressbarFillClass);
        } else {
          paginationHTML = `<span class="${params.progressbarFillClass}"></span>`;
        }
      }
      spswiper.pagination.bullets = [];
      el.forEach(subEl => {
        if (params.type !== 'custom') {
          subEl.innerHTML = paginationHTML || '';
        }
        if (params.type === 'bullets') {
          spswiper.pagination.bullets.push(...subEl.querySelectorAll(classesToSelector(params.bulletClass)));
        }
      });
      if (params.type !== 'custom') {
        emit('paginationRender', el[0]);
      }
    }
    function init() {
      spswiper.params.pagination = createElementIfNotDefined(spswiper, spswiper.originalParams.pagination, spswiper.params.pagination, {
        el: 'spswiper-pagination'
      });
      const params = spswiper.params.pagination;
      if (!params.el) return;
      let el;
      if (typeof params.el === 'string' && spswiper.isElement) {
        el = spswiper.el.querySelector(params.el);
      }
      if (!el && typeof params.el === 'string') {
        el = [...document.querySelectorAll(params.el)];
      }
      if (!el) {
        el = params.el;
      }
      if (!el || el.length === 0) return;
      if (spswiper.params.uniqueNavElements && typeof params.el === 'string' && Array.isArray(el) && el.length > 1) {
        el = [...spswiper.el.querySelectorAll(params.el)];
        // check if it belongs to another nested SPSwiper
        if (el.length > 1) {
          el = el.filter(subEl => {
            if (elementParents(subEl, '.spswiper')[0] !== spswiper.el) return false;
            return true;
          })[0];
        }
      }
      if (Array.isArray(el) && el.length === 1) el = el[0];
      Object.assign(spswiper.pagination, {
        el
      });
      el = makeElementsArray(el);
      el.forEach(subEl => {
        if (params.type === 'bullets' && params.clickable) {
          subEl.classList.add(...(params.clickableClass || '').split(' '));
        }
        subEl.classList.add(params.modifierClass + params.type);
        subEl.classList.add(spswiper.isHorizontal() ? params.horizontalClass : params.verticalClass);
        if (params.type === 'bullets' && params.dynamicBullets) {
          subEl.classList.add(`${params.modifierClass}${params.type}-dynamic`);
          dynamicBulletIndex = 0;
          if (params.dynamicMainBullets < 1) {
            params.dynamicMainBullets = 1;
          }
        }
        if (params.type === 'progressbar' && params.progressbarOpposite) {
          subEl.classList.add(params.progressbarOppositeClass);
        }
        if (params.clickable) {
          subEl.addEventListener('click', onBulletClick);
        }
        if (!spswiper.enabled) {
          subEl.classList.add(params.lockClass);
        }
      });
    }
    function destroy() {
      const params = spswiper.params.pagination;
      if (isPaginationDisabled()) return;
      let el = spswiper.pagination.el;
      if (el) {
        el = makeElementsArray(el);
        el.forEach(subEl => {
          subEl.classList.remove(params.hiddenClass);
          subEl.classList.remove(params.modifierClass + params.type);
          subEl.classList.remove(spswiper.isHorizontal() ? params.horizontalClass : params.verticalClass);
          if (params.clickable) {
            subEl.classList.remove(...(params.clickableClass || '').split(' '));
            subEl.removeEventListener('click', onBulletClick);
          }
        });
      }
      if (spswiper.pagination.bullets) spswiper.pagination.bullets.forEach(subEl => subEl.classList.remove(...params.bulletActiveClass.split(' ')));
    }
    on('changeDirection', () => {
      if (!spswiper.pagination || !spswiper.pagination.el) return;
      const params = spswiper.params.pagination;
      let {
        el
      } = spswiper.pagination;
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.classList.remove(params.horizontalClass, params.verticalClass);
        subEl.classList.add(spswiper.isHorizontal() ? params.horizontalClass : params.verticalClass);
      });
    });
    on('init', () => {
      if (spswiper.params.pagination.enabled === false) {
        // eslint-disable-next-line
        disable();
      } else {
        init();
        render();
        update();
      }
    });
    on('activeIndexChange', () => {
      if (typeof spswiper.snapIndex === 'undefined') {
        update();
      }
    });
    on('snapIndexChange', () => {
      update();
    });
    on('snapGridLengthChange', () => {
      render();
      update();
    });
    on('destroy', () => {
      destroy();
    });
    on('enable disable', () => {
      let {
        el
      } = spswiper.pagination;
      if (el) {
        el = makeElementsArray(el);
        el.forEach(subEl => subEl.classList[spswiper.enabled ? 'remove' : 'add'](spswiper.params.pagination.lockClass));
      }
    });
    on('lock unlock', () => {
      update();
    });
    on('click', (_s, e) => {
      const targetEl = e.target;
      const el = makeElementsArray(spswiper.pagination.el);
      if (spswiper.params.pagination.el && spswiper.params.pagination.hideOnClick && el && el.length > 0 && !targetEl.classList.contains(spswiper.params.pagination.bulletClass)) {
        if (spswiper.navigation && (spswiper.navigation.nextEl && targetEl === spswiper.navigation.nextEl || spswiper.navigation.prevEl && targetEl === spswiper.navigation.prevEl)) return;
        const isHidden = el[0].classList.contains(spswiper.params.pagination.hiddenClass);
        if (isHidden === true) {
          emit('paginationShow');
        } else {
          emit('paginationHide');
        }
        el.forEach(subEl => subEl.classList.toggle(spswiper.params.pagination.hiddenClass));
      }
    });
    const enable = () => {
      spswiper.el.classList.remove(spswiper.params.pagination.paginationDisabledClass);
      let {
        el
      } = spswiper.pagination;
      if (el) {
        el = makeElementsArray(el);
        el.forEach(subEl => subEl.classList.remove(spswiper.params.pagination.paginationDisabledClass));
      }
      init();
      render();
      update();
    };
    const disable = () => {
      spswiper.el.classList.add(spswiper.params.pagination.paginationDisabledClass);
      let {
        el
      } = spswiper.pagination;
      if (el) {
        el = makeElementsArray(el);
        el.forEach(subEl => subEl.classList.add(spswiper.params.pagination.paginationDisabledClass));
      }
      destroy();
    };
    Object.assign(spswiper.pagination, {
      enable,
      disable,
      render,
      update,
      init,
      destroy
    });
  }

  function Scrollbar(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit
    } = _ref;
    const document = getDocument();
    let isTouched = false;
    let timeout = null;
    let dragTimeout = null;
    let dragStartPos;
    let dragSize;
    let trackSize;
    let divider;
    extendParams({
      scrollbar: {
        el: null,
        dragSize: 'auto',
        hide: false,
        draggable: false,
        snapOnRelease: true,
        lockClass: 'spswiper-scrollbar-lock',
        dragClass: 'spswiper-scrollbar-drag',
        scrollbarDisabledClass: 'spswiper-scrollbar-disabled',
        horizontalClass: `spswiper-scrollbar-horizontal`,
        verticalClass: `spswiper-scrollbar-vertical`
      }
    });
    spswiper.scrollbar = {
      el: null,
      dragEl: null
    };
    function setTranslate() {
      if (!spswiper.params.scrollbar.el || !spswiper.scrollbar.el) return;
      const {
        scrollbar,
        rtlTranslate: rtl
      } = spswiper;
      const {
        dragEl,
        el
      } = scrollbar;
      const params = spswiper.params.scrollbar;
      const progress = spswiper.params.loop ? spswiper.progressLoop : spswiper.progress;
      let newSize = dragSize;
      let newPos = (trackSize - dragSize) * progress;
      if (rtl) {
        newPos = -newPos;
        if (newPos > 0) {
          newSize = dragSize - newPos;
          newPos = 0;
        } else if (-newPos + dragSize > trackSize) {
          newSize = trackSize + newPos;
        }
      } else if (newPos < 0) {
        newSize = dragSize + newPos;
        newPos = 0;
      } else if (newPos + dragSize > trackSize) {
        newSize = trackSize - newPos;
      }
      if (spswiper.isHorizontal()) {
        dragEl.style.transform = `translate3d(${newPos}px, 0, 0)`;
        dragEl.style.width = `${newSize}px`;
      } else {
        dragEl.style.transform = `translate3d(0px, ${newPos}px, 0)`;
        dragEl.style.height = `${newSize}px`;
      }
      if (params.hide) {
        clearTimeout(timeout);
        el.style.opacity = 1;
        timeout = setTimeout(() => {
          el.style.opacity = 0;
          el.style.transitionDuration = '400ms';
        }, 1000);
      }
    }
    function setTransition(duration) {
      if (!spswiper.params.scrollbar.el || !spswiper.scrollbar.el) return;
      spswiper.scrollbar.dragEl.style.transitionDuration = `${duration}ms`;
    }
    function updateSize() {
      if (!spswiper.params.scrollbar.el || !spswiper.scrollbar.el) return;
      const {
        scrollbar
      } = spswiper;
      const {
        dragEl,
        el
      } = scrollbar;
      dragEl.style.width = '';
      dragEl.style.height = '';
      trackSize = spswiper.isHorizontal() ? el.offsetWidth : el.offsetHeight;
      divider = spswiper.size / (spswiper.virtualSize + spswiper.params.slidesOffsetBefore - (spswiper.params.centeredSlides ? spswiper.snapGrid[0] : 0));
      if (spswiper.params.scrollbar.dragSize === 'auto') {
        dragSize = trackSize * divider;
      } else {
        dragSize = parseInt(spswiper.params.scrollbar.dragSize, 10);
      }
      if (spswiper.isHorizontal()) {
        dragEl.style.width = `${dragSize}px`;
      } else {
        dragEl.style.height = `${dragSize}px`;
      }
      if (divider >= 1) {
        el.style.display = 'none';
      } else {
        el.style.display = '';
      }
      if (spswiper.params.scrollbar.hide) {
        el.style.opacity = 0;
      }
      if (spswiper.params.watchOverflow && spswiper.enabled) {
        scrollbar.el.classList[spswiper.isLocked ? 'add' : 'remove'](spswiper.params.scrollbar.lockClass);
      }
    }
    function getPointerPosition(e) {
      return spswiper.isHorizontal() ? e.clientX : e.clientY;
    }
    function setDragPosition(e) {
      const {
        scrollbar,
        rtlTranslate: rtl
      } = spswiper;
      const {
        el
      } = scrollbar;
      let positionRatio;
      positionRatio = (getPointerPosition(e) - elementOffset(el)[spswiper.isHorizontal() ? 'left' : 'top'] - (dragStartPos !== null ? dragStartPos : dragSize / 2)) / (trackSize - dragSize);
      positionRatio = Math.max(Math.min(positionRatio, 1), 0);
      if (rtl) {
        positionRatio = 1 - positionRatio;
      }
      const position = spswiper.minTranslate() + (spswiper.maxTranslate() - spswiper.minTranslate()) * positionRatio;
      spswiper.updateProgress(position);
      spswiper.setTranslate(position);
      spswiper.updateActiveIndex();
      spswiper.updateSlidesClasses();
    }
    function onDragStart(e) {
      const params = spswiper.params.scrollbar;
      const {
        scrollbar,
        wrapperEl
      } = spswiper;
      const {
        el,
        dragEl
      } = scrollbar;
      isTouched = true;
      dragStartPos = e.target === dragEl ? getPointerPosition(e) - e.target.getBoundingClientRect()[spswiper.isHorizontal() ? 'left' : 'top'] : null;
      e.preventDefault();
      e.stopPropagation();
      wrapperEl.style.transitionDuration = '100ms';
      dragEl.style.transitionDuration = '100ms';
      setDragPosition(e);
      clearTimeout(dragTimeout);
      el.style.transitionDuration = '0ms';
      if (params.hide) {
        el.style.opacity = 1;
      }
      if (spswiper.params.cssMode) {
        spswiper.wrapperEl.style['scroll-snap-type'] = 'none';
      }
      emit('scrollbarDragStart', e);
    }
    function onDragMove(e) {
      const {
        scrollbar,
        wrapperEl
      } = spswiper;
      const {
        el,
        dragEl
      } = scrollbar;
      if (!isTouched) return;
      if (e.preventDefault) e.preventDefault();else e.returnValue = false;
      setDragPosition(e);
      wrapperEl.style.transitionDuration = '0ms';
      el.style.transitionDuration = '0ms';
      dragEl.style.transitionDuration = '0ms';
      emit('scrollbarDragMove', e);
    }
    function onDragEnd(e) {
      const params = spswiper.params.scrollbar;
      const {
        scrollbar,
        wrapperEl
      } = spswiper;
      const {
        el
      } = scrollbar;
      if (!isTouched) return;
      isTouched = false;
      if (spswiper.params.cssMode) {
        spswiper.wrapperEl.style['scroll-snap-type'] = '';
        wrapperEl.style.transitionDuration = '';
      }
      if (params.hide) {
        clearTimeout(dragTimeout);
        dragTimeout = nextTick(() => {
          el.style.opacity = 0;
          el.style.transitionDuration = '400ms';
        }, 1000);
      }
      emit('scrollbarDragEnd', e);
      if (params.snapOnRelease) {
        spswiper.slideToClosest();
      }
    }
    function events(method) {
      const {
        scrollbar,
        params
      } = spswiper;
      const el = scrollbar.el;
      if (!el) return;
      const target = el;
      const activeListener = params.passiveListeners ? {
        passive: false,
        capture: false
      } : false;
      const passiveListener = params.passiveListeners ? {
        passive: true,
        capture: false
      } : false;
      if (!target) return;
      const eventMethod = method === 'on' ? 'addEventListener' : 'removeEventListener';
      target[eventMethod]('pointerdown', onDragStart, activeListener);
      document[eventMethod]('pointermove', onDragMove, activeListener);
      document[eventMethod]('pointerup', onDragEnd, passiveListener);
    }
    function enableDraggable() {
      if (!spswiper.params.scrollbar.el || !spswiper.scrollbar.el) return;
      events('on');
    }
    function disableDraggable() {
      if (!spswiper.params.scrollbar.el || !spswiper.scrollbar.el) return;
      events('off');
    }
    function init() {
      const {
        scrollbar,
        el: spswiperEl
      } = spswiper;
      spswiper.params.scrollbar = createElementIfNotDefined(spswiper, spswiper.originalParams.scrollbar, spswiper.params.scrollbar, {
        el: 'spswiper-scrollbar'
      });
      const params = spswiper.params.scrollbar;
      if (!params.el) return;
      let el;
      if (typeof params.el === 'string' && spswiper.isElement) {
        el = spswiper.el.querySelector(params.el);
      }
      if (!el && typeof params.el === 'string') {
        el = document.querySelectorAll(params.el);
      } else if (!el) {
        el = params.el;
      }
      if (spswiper.params.uniqueNavElements && typeof params.el === 'string' && el.length > 1 && spswiperEl.querySelectorAll(params.el).length === 1) {
        el = spswiperEl.querySelector(params.el);
      }
      if (el.length > 0) el = el[0];
      el.classList.add(spswiper.isHorizontal() ? params.horizontalClass : params.verticalClass);
      let dragEl;
      if (el) {
        dragEl = el.querySelector(`.${spswiper.params.scrollbar.dragClass}`);
        if (!dragEl) {
          dragEl = createElement('div', spswiper.params.scrollbar.dragClass);
          el.append(dragEl);
        }
      }
      Object.assign(scrollbar, {
        el,
        dragEl
      });
      if (params.draggable) {
        enableDraggable();
      }
      if (el) {
        el.classList[spswiper.enabled ? 'remove' : 'add'](spswiper.params.scrollbar.lockClass);
      }
    }
    function destroy() {
      const params = spswiper.params.scrollbar;
      const el = spswiper.scrollbar.el;
      if (el) {
        el.classList.remove(spswiper.isHorizontal() ? params.horizontalClass : params.verticalClass);
      }
      disableDraggable();
    }
    on('init', () => {
      if (spswiper.params.scrollbar.enabled === false) {
        // eslint-disable-next-line
        disable();
      } else {
        init();
        updateSize();
        setTranslate();
      }
    });
    on('update resize observerUpdate lock unlock', () => {
      updateSize();
    });
    on('setTranslate', () => {
      setTranslate();
    });
    on('setTransition', (_s, duration) => {
      setTransition(duration);
    });
    on('enable disable', () => {
      const {
        el
      } = spswiper.scrollbar;
      if (el) {
        el.classList[spswiper.enabled ? 'remove' : 'add'](spswiper.params.scrollbar.lockClass);
      }
    });
    on('destroy', () => {
      destroy();
    });
    const enable = () => {
      spswiper.el.classList.remove(spswiper.params.scrollbar.scrollbarDisabledClass);
      if (spswiper.scrollbar.el) {
        spswiper.scrollbar.el.classList.remove(spswiper.params.scrollbar.scrollbarDisabledClass);
      }
      init();
      updateSize();
      setTranslate();
    };
    const disable = () => {
      spswiper.el.classList.add(spswiper.params.scrollbar.scrollbarDisabledClass);
      if (spswiper.scrollbar.el) {
        spswiper.scrollbar.el.classList.add(spswiper.params.scrollbar.scrollbarDisabledClass);
      }
      destroy();
    };
    Object.assign(spswiper.scrollbar, {
      enable,
      disable,
      updateSize,
      setTranslate,
      init,
      destroy
    });
  }

  function Parallax(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      parallax: {
        enabled: false
      }
    });
    const elementsSelector = '[data-spswiper-parallax], [data-spswiper-parallax-x], [data-spswiper-parallax-y], [data-spswiper-parallax-opacity], [data-spswiper-parallax-scale]';
    const setTransform = (el, progress) => {
      const {
        rtl
      } = spswiper;
      const rtlFactor = rtl ? -1 : 1;
      const p = el.getAttribute('data-spswiper-parallax') || '0';
      let x = el.getAttribute('data-spswiper-parallax-x');
      let y = el.getAttribute('data-spswiper-parallax-y');
      const scale = el.getAttribute('data-spswiper-parallax-scale');
      const opacity = el.getAttribute('data-spswiper-parallax-opacity');
      const rotate = el.getAttribute('data-spswiper-parallax-rotate');
      if (x || y) {
        x = x || '0';
        y = y || '0';
      } else if (spswiper.isHorizontal()) {
        x = p;
        y = '0';
      } else {
        y = p;
        x = '0';
      }
      if (x.indexOf('%') >= 0) {
        x = `${parseInt(x, 10) * progress * rtlFactor}%`;
      } else {
        x = `${x * progress * rtlFactor}px`;
      }
      if (y.indexOf('%') >= 0) {
        y = `${parseInt(y, 10) * progress}%`;
      } else {
        y = `${y * progress}px`;
      }
      if (typeof opacity !== 'undefined' && opacity !== null) {
        const currentOpacity = opacity - (opacity - 1) * (1 - Math.abs(progress));
        el.style.opacity = currentOpacity;
      }
      let transform = `translate3d(${x}, ${y}, 0px)`;
      if (typeof scale !== 'undefined' && scale !== null) {
        const currentScale = scale - (scale - 1) * (1 - Math.abs(progress));
        transform += ` scale(${currentScale})`;
      }
      if (rotate && typeof rotate !== 'undefined' && rotate !== null) {
        const currentRotate = rotate * progress * -1;
        transform += ` rotate(${currentRotate}deg)`;
      }
      el.style.transform = transform;
    };
    const setTranslate = () => {
      const {
        el,
        slides,
        progress,
        snapGrid,
        isElement
      } = spswiper;
      const elements = elementChildren(el, elementsSelector);
      if (spswiper.isElement) {
        elements.push(...elementChildren(spswiper.hostEl, elementsSelector));
      }
      elements.forEach(subEl => {
        setTransform(subEl, progress);
      });
      slides.forEach((slideEl, slideIndex) => {
        let slideProgress = slideEl.progress;
        if (spswiper.params.slidesPerGroup > 1 && spswiper.params.slidesPerView !== 'auto') {
          slideProgress += Math.ceil(slideIndex / 2) - progress * (snapGrid.length - 1);
        }
        slideProgress = Math.min(Math.max(slideProgress, -1), 1);
        slideEl.querySelectorAll(`${elementsSelector}, [data-spswiper-parallax-rotate]`).forEach(subEl => {
          setTransform(subEl, slideProgress);
        });
      });
    };
    const setTransition = function (duration) {
      if (duration === void 0) {
        duration = spswiper.params.speed;
      }
      const {
        el,
        hostEl
      } = spswiper;
      const elements = [...el.querySelectorAll(elementsSelector)];
      if (spswiper.isElement) {
        elements.push(...hostEl.querySelectorAll(elementsSelector));
      }
      elements.forEach(parallaxEl => {
        let parallaxDuration = parseInt(parallaxEl.getAttribute('data-spswiper-parallax-duration'), 10) || duration;
        if (duration === 0) parallaxDuration = 0;
        parallaxEl.style.transitionDuration = `${parallaxDuration}ms`;
      });
    };
    on('beforeInit', () => {
      if (!spswiper.params.parallax.enabled) return;
      spswiper.params.watchSlidesProgress = true;
      spswiper.originalParams.watchSlidesProgress = true;
    });
    on('init', () => {
      if (!spswiper.params.parallax.enabled) return;
      setTranslate();
    });
    on('setTranslate', () => {
      if (!spswiper.params.parallax.enabled) return;
      setTranslate();
    });
    on('setTransition', (_spswiper, duration) => {
      if (!spswiper.params.parallax.enabled) return;
      setTransition(duration);
    });
  }

  function Zoom(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit
    } = _ref;
    const window = getWindow();
    extendParams({
      zoom: {
        enabled: false,
        maxRatio: 3,
        minRatio: 1,
        toggle: true,
        containerClass: 'spswiper-zoom-container',
        zoomedSlideClass: 'spswiper-slide-zoomed'
      }
    });
    spswiper.zoom = {
      enabled: false
    };
    let currentScale = 1;
    let isScaling = false;
    let fakeGestureTouched;
    let fakeGestureMoved;
    const evCache = [];
    const gesture = {
      originX: 0,
      originY: 0,
      slideEl: undefined,
      slideWidth: undefined,
      slideHeight: undefined,
      imageEl: undefined,
      imageWrapEl: undefined,
      maxRatio: 3
    };
    const image = {
      isTouched: undefined,
      isMoved: undefined,
      currentX: undefined,
      currentY: undefined,
      minX: undefined,
      minY: undefined,
      maxX: undefined,
      maxY: undefined,
      width: undefined,
      height: undefined,
      startX: undefined,
      startY: undefined,
      touchesStart: {},
      touchesCurrent: {}
    };
    const velocity = {
      x: undefined,
      y: undefined,
      prevPositionX: undefined,
      prevPositionY: undefined,
      prevTime: undefined
    };
    let scale = 1;
    Object.defineProperty(spswiper.zoom, 'scale', {
      get() {
        return scale;
      },
      set(value) {
        if (scale !== value) {
          const imageEl = gesture.imageEl;
          const slideEl = gesture.slideEl;
          emit('zoomChange', value, imageEl, slideEl);
        }
        scale = value;
      }
    });
    function getDistanceBetweenTouches() {
      if (evCache.length < 2) return 1;
      const x1 = evCache[0].pageX;
      const y1 = evCache[0].pageY;
      const x2 = evCache[1].pageX;
      const y2 = evCache[1].pageY;
      const distance = Math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2);
      return distance;
    }
    function getScaleOrigin() {
      if (evCache.length < 2) return {
        x: null,
        y: null
      };
      const box = gesture.imageEl.getBoundingClientRect();
      return [(evCache[0].pageX + (evCache[1].pageX - evCache[0].pageX) / 2 - box.x - window.scrollX) / currentScale, (evCache[0].pageY + (evCache[1].pageY - evCache[0].pageY) / 2 - box.y - window.scrollY) / currentScale];
    }
    function getSlideSelector() {
      return spswiper.isElement ? `spswiper-slide` : `.${spswiper.params.slideClass}`;
    }
    function eventWithinSlide(e) {
      const slideSelector = getSlideSelector();
      if (e.target.matches(slideSelector)) return true;
      if (spswiper.slides.filter(slideEl => slideEl.contains(e.target)).length > 0) return true;
      return false;
    }
    function eventWithinZoomContainer(e) {
      const selector = `.${spswiper.params.zoom.containerClass}`;
      if (e.target.matches(selector)) return true;
      if ([...spswiper.hostEl.querySelectorAll(selector)].filter(containerEl => containerEl.contains(e.target)).length > 0) return true;
      return false;
    }

    // Events
    function onGestureStart(e) {
      if (e.pointerType === 'mouse') {
        evCache.splice(0, evCache.length);
      }
      if (!eventWithinSlide(e)) return;
      const params = spswiper.params.zoom;
      fakeGestureTouched = false;
      fakeGestureMoved = false;
      evCache.push(e);
      if (evCache.length < 2) {
        return;
      }
      fakeGestureTouched = true;
      gesture.scaleStart = getDistanceBetweenTouches();
      if (!gesture.slideEl) {
        gesture.slideEl = e.target.closest(`.${spswiper.params.slideClass}, spswiper-slide`);
        if (!gesture.slideEl) gesture.slideEl = spswiper.slides[spswiper.activeIndex];
        let imageEl = gesture.slideEl.querySelector(`.${params.containerClass}`);
        if (imageEl) {
          imageEl = imageEl.querySelectorAll('picture, img, svg, canvas, .spswiper-zoom-target')[0];
        }
        gesture.imageEl = imageEl;
        if (imageEl) {
          gesture.imageWrapEl = elementParents(gesture.imageEl, `.${params.containerClass}`)[0];
        } else {
          gesture.imageWrapEl = undefined;
        }
        if (!gesture.imageWrapEl) {
          gesture.imageEl = undefined;
          return;
        }
        gesture.maxRatio = gesture.imageWrapEl.getAttribute('data-spswiper-zoom') || params.maxRatio;
      }
      if (gesture.imageEl) {
        const [originX, originY] = getScaleOrigin();
        gesture.originX = originX;
        gesture.originY = originY;
        gesture.imageEl.style.transitionDuration = '0ms';
      }
      isScaling = true;
    }
    function onGestureChange(e) {
      if (!eventWithinSlide(e)) return;
      const params = spswiper.params.zoom;
      const zoom = spswiper.zoom;
      const pointerIndex = evCache.findIndex(cachedEv => cachedEv.pointerId === e.pointerId);
      if (pointerIndex >= 0) evCache[pointerIndex] = e;
      if (evCache.length < 2) {
        return;
      }
      fakeGestureMoved = true;
      gesture.scaleMove = getDistanceBetweenTouches();
      if (!gesture.imageEl) {
        return;
      }
      zoom.scale = gesture.scaleMove / gesture.scaleStart * currentScale;
      if (zoom.scale > gesture.maxRatio) {
        zoom.scale = gesture.maxRatio - 1 + (zoom.scale - gesture.maxRatio + 1) ** 0.5;
      }
      if (zoom.scale < params.minRatio) {
        zoom.scale = params.minRatio + 1 - (params.minRatio - zoom.scale + 1) ** 0.5;
      }
      gesture.imageEl.style.transform = `translate3d(0,0,0) scale(${zoom.scale})`;
    }
    function onGestureEnd(e) {
      if (!eventWithinSlide(e)) return;
      if (e.pointerType === 'mouse' && e.type === 'pointerout') return;
      const params = spswiper.params.zoom;
      const zoom = spswiper.zoom;
      const pointerIndex = evCache.findIndex(cachedEv => cachedEv.pointerId === e.pointerId);
      if (pointerIndex >= 0) evCache.splice(pointerIndex, 1);
      if (!fakeGestureTouched || !fakeGestureMoved) {
        return;
      }
      fakeGestureTouched = false;
      fakeGestureMoved = false;
      if (!gesture.imageEl) return;
      zoom.scale = Math.max(Math.min(zoom.scale, gesture.maxRatio), params.minRatio);
      gesture.imageEl.style.transitionDuration = `${spswiper.params.speed}ms`;
      gesture.imageEl.style.transform = `translate3d(0,0,0) scale(${zoom.scale})`;
      currentScale = zoom.scale;
      isScaling = false;
      if (zoom.scale > 1 && gesture.slideEl) {
        gesture.slideEl.classList.add(`${params.zoomedSlideClass}`);
      } else if (zoom.scale <= 1 && gesture.slideEl) {
        gesture.slideEl.classList.remove(`${params.zoomedSlideClass}`);
      }
      if (zoom.scale === 1) {
        gesture.originX = 0;
        gesture.originY = 0;
        gesture.slideEl = undefined;
      }
    }
    function onTouchStart(e) {
      const device = spswiper.device;
      if (!gesture.imageEl) return;
      if (image.isTouched) return;
      if (device.android && e.cancelable) e.preventDefault();
      image.isTouched = true;
      const event = evCache.length > 0 ? evCache[0] : e;
      image.touchesStart.x = event.pageX;
      image.touchesStart.y = event.pageY;
    }
    function onTouchMove(e) {
      if (!eventWithinSlide(e) || !eventWithinZoomContainer(e)) return;
      const zoom = spswiper.zoom;
      if (!gesture.imageEl) return;
      if (!image.isTouched || !gesture.slideEl) return;
      if (!image.isMoved) {
        image.width = gesture.imageEl.offsetWidth;
        image.height = gesture.imageEl.offsetHeight;
        image.startX = getTranslate(gesture.imageWrapEl, 'x') || 0;
        image.startY = getTranslate(gesture.imageWrapEl, 'y') || 0;
        gesture.slideWidth = gesture.slideEl.offsetWidth;
        gesture.slideHeight = gesture.slideEl.offsetHeight;
        gesture.imageWrapEl.style.transitionDuration = '0ms';
      }
      // Define if we need image drag
      const scaledWidth = image.width * zoom.scale;
      const scaledHeight = image.height * zoom.scale;
      if (scaledWidth < gesture.slideWidth && scaledHeight < gesture.slideHeight) return;
      image.minX = Math.min(gesture.slideWidth / 2 - scaledWidth / 2, 0);
      image.maxX = -image.minX;
      image.minY = Math.min(gesture.slideHeight / 2 - scaledHeight / 2, 0);
      image.maxY = -image.minY;
      image.touchesCurrent.x = evCache.length > 0 ? evCache[0].pageX : e.pageX;
      image.touchesCurrent.y = evCache.length > 0 ? evCache[0].pageY : e.pageY;
      const touchesDiff = Math.max(Math.abs(image.touchesCurrent.x - image.touchesStart.x), Math.abs(image.touchesCurrent.y - image.touchesStart.y));
      if (touchesDiff > 5) {
        spswiper.allowClick = false;
      }
      if (!image.isMoved && !isScaling) {
        if (spswiper.isHorizontal() && (Math.floor(image.minX) === Math.floor(image.startX) && image.touchesCurrent.x < image.touchesStart.x || Math.floor(image.maxX) === Math.floor(image.startX) && image.touchesCurrent.x > image.touchesStart.x)) {
          image.isTouched = false;
          return;
        }
        if (!spswiper.isHorizontal() && (Math.floor(image.minY) === Math.floor(image.startY) && image.touchesCurrent.y < image.touchesStart.y || Math.floor(image.maxY) === Math.floor(image.startY) && image.touchesCurrent.y > image.touchesStart.y)) {
          image.isTouched = false;
          return;
        }
      }
      if (e.cancelable) {
        e.preventDefault();
      }
      e.stopPropagation();
      image.isMoved = true;
      const scaleRatio = (zoom.scale - currentScale) / (gesture.maxRatio - spswiper.params.zoom.minRatio);
      const {
        originX,
        originY
      } = gesture;
      image.currentX = image.touchesCurrent.x - image.touchesStart.x + image.startX + scaleRatio * (image.width - originX * 2);
      image.currentY = image.touchesCurrent.y - image.touchesStart.y + image.startY + scaleRatio * (image.height - originY * 2);
      if (image.currentX < image.minX) {
        image.currentX = image.minX + 1 - (image.minX - image.currentX + 1) ** 0.8;
      }
      if (image.currentX > image.maxX) {
        image.currentX = image.maxX - 1 + (image.currentX - image.maxX + 1) ** 0.8;
      }
      if (image.currentY < image.minY) {
        image.currentY = image.minY + 1 - (image.minY - image.currentY + 1) ** 0.8;
      }
      if (image.currentY > image.maxY) {
        image.currentY = image.maxY - 1 + (image.currentY - image.maxY + 1) ** 0.8;
      }

      // Velocity
      if (!velocity.prevPositionX) velocity.prevPositionX = image.touchesCurrent.x;
      if (!velocity.prevPositionY) velocity.prevPositionY = image.touchesCurrent.y;
      if (!velocity.prevTime) velocity.prevTime = Date.now();
      velocity.x = (image.touchesCurrent.x - velocity.prevPositionX) / (Date.now() - velocity.prevTime) / 2;
      velocity.y = (image.touchesCurrent.y - velocity.prevPositionY) / (Date.now() - velocity.prevTime) / 2;
      if (Math.abs(image.touchesCurrent.x - velocity.prevPositionX) < 2) velocity.x = 0;
      if (Math.abs(image.touchesCurrent.y - velocity.prevPositionY) < 2) velocity.y = 0;
      velocity.prevPositionX = image.touchesCurrent.x;
      velocity.prevPositionY = image.touchesCurrent.y;
      velocity.prevTime = Date.now();
      gesture.imageWrapEl.style.transform = `translate3d(${image.currentX}px, ${image.currentY}px,0)`;
    }
    function onTouchEnd() {
      const zoom = spswiper.zoom;
      if (!gesture.imageEl) return;
      if (!image.isTouched || !image.isMoved) {
        image.isTouched = false;
        image.isMoved = false;
        return;
      }
      image.isTouched = false;
      image.isMoved = false;
      let momentumDurationX = 300;
      let momentumDurationY = 300;
      const momentumDistanceX = velocity.x * momentumDurationX;
      const newPositionX = image.currentX + momentumDistanceX;
      const momentumDistanceY = velocity.y * momentumDurationY;
      const newPositionY = image.currentY + momentumDistanceY;

      // Fix duration
      if (velocity.x !== 0) momentumDurationX = Math.abs((newPositionX - image.currentX) / velocity.x);
      if (velocity.y !== 0) momentumDurationY = Math.abs((newPositionY - image.currentY) / velocity.y);
      const momentumDuration = Math.max(momentumDurationX, momentumDurationY);
      image.currentX = newPositionX;
      image.currentY = newPositionY;
      // Define if we need image drag
      const scaledWidth = image.width * zoom.scale;
      const scaledHeight = image.height * zoom.scale;
      image.minX = Math.min(gesture.slideWidth / 2 - scaledWidth / 2, 0);
      image.maxX = -image.minX;
      image.minY = Math.min(gesture.slideHeight / 2 - scaledHeight / 2, 0);
      image.maxY = -image.minY;
      image.currentX = Math.max(Math.min(image.currentX, image.maxX), image.minX);
      image.currentY = Math.max(Math.min(image.currentY, image.maxY), image.minY);
      gesture.imageWrapEl.style.transitionDuration = `${momentumDuration}ms`;
      gesture.imageWrapEl.style.transform = `translate3d(${image.currentX}px, ${image.currentY}px,0)`;
    }
    function onTransitionEnd() {
      const zoom = spswiper.zoom;
      if (gesture.slideEl && spswiper.activeIndex !== spswiper.slides.indexOf(gesture.slideEl)) {
        if (gesture.imageEl) {
          gesture.imageEl.style.transform = 'translate3d(0,0,0) scale(1)';
        }
        if (gesture.imageWrapEl) {
          gesture.imageWrapEl.style.transform = 'translate3d(0,0,0)';
        }
        gesture.slideEl.classList.remove(`${spswiper.params.zoom.zoomedSlideClass}`);
        zoom.scale = 1;
        currentScale = 1;
        gesture.slideEl = undefined;
        gesture.imageEl = undefined;
        gesture.imageWrapEl = undefined;
        gesture.originX = 0;
        gesture.originY = 0;
      }
    }
    function zoomIn(e) {
      const zoom = spswiper.zoom;
      const params = spswiper.params.zoom;
      if (!gesture.slideEl) {
        if (e && e.target) {
          gesture.slideEl = e.target.closest(`.${spswiper.params.slideClass}, spswiper-slide`);
        }
        if (!gesture.slideEl) {
          if (spswiper.params.virtual && spswiper.params.virtual.enabled && spswiper.virtual) {
            gesture.slideEl = elementChildren(spswiper.slidesEl, `.${spswiper.params.slideActiveClass}`)[0];
          } else {
            gesture.slideEl = spswiper.slides[spswiper.activeIndex];
          }
        }
        let imageEl = gesture.slideEl.querySelector(`.${params.containerClass}`);
        if (imageEl) {
          imageEl = imageEl.querySelectorAll('picture, img, svg, canvas, .spswiper-zoom-target')[0];
        }
        gesture.imageEl = imageEl;
        if (imageEl) {
          gesture.imageWrapEl = elementParents(gesture.imageEl, `.${params.containerClass}`)[0];
        } else {
          gesture.imageWrapEl = undefined;
        }
      }
      if (!gesture.imageEl || !gesture.imageWrapEl) return;
      if (spswiper.params.cssMode) {
        spswiper.wrapperEl.style.overflow = 'hidden';
        spswiper.wrapperEl.style.touchAction = 'none';
      }
      gesture.slideEl.classList.add(`${params.zoomedSlideClass}`);
      let touchX;
      let touchY;
      let offsetX;
      let offsetY;
      let diffX;
      let diffY;
      let translateX;
      let translateY;
      let imageWidth;
      let imageHeight;
      let scaledWidth;
      let scaledHeight;
      let translateMinX;
      let translateMinY;
      let translateMaxX;
      let translateMaxY;
      let slideWidth;
      let slideHeight;
      if (typeof image.touchesStart.x === 'undefined' && e) {
        touchX = e.pageX;
        touchY = e.pageY;
      } else {
        touchX = image.touchesStart.x;
        touchY = image.touchesStart.y;
      }
      const forceZoomRatio = typeof e === 'number' ? e : null;
      if (currentScale === 1 && forceZoomRatio) {
        touchX = undefined;
        touchY = undefined;
      }
      zoom.scale = forceZoomRatio || gesture.imageWrapEl.getAttribute('data-spswiper-zoom') || params.maxRatio;
      currentScale = forceZoomRatio || gesture.imageWrapEl.getAttribute('data-spswiper-zoom') || params.maxRatio;
      if (e && !(currentScale === 1 && forceZoomRatio)) {
        slideWidth = gesture.slideEl.offsetWidth;
        slideHeight = gesture.slideEl.offsetHeight;
        offsetX = elementOffset(gesture.slideEl).left + window.scrollX;
        offsetY = elementOffset(gesture.slideEl).top + window.scrollY;
        diffX = offsetX + slideWidth / 2 - touchX;
        diffY = offsetY + slideHeight / 2 - touchY;
        imageWidth = gesture.imageEl.offsetWidth;
        imageHeight = gesture.imageEl.offsetHeight;
        scaledWidth = imageWidth * zoom.scale;
        scaledHeight = imageHeight * zoom.scale;
        translateMinX = Math.min(slideWidth / 2 - scaledWidth / 2, 0);
        translateMinY = Math.min(slideHeight / 2 - scaledHeight / 2, 0);
        translateMaxX = -translateMinX;
        translateMaxY = -translateMinY;
        translateX = diffX * zoom.scale;
        translateY = diffY * zoom.scale;
        if (translateX < translateMinX) {
          translateX = translateMinX;
        }
        if (translateX > translateMaxX) {
          translateX = translateMaxX;
        }
        if (translateY < translateMinY) {
          translateY = translateMinY;
        }
        if (translateY > translateMaxY) {
          translateY = translateMaxY;
        }
      } else {
        translateX = 0;
        translateY = 0;
      }
      if (forceZoomRatio && zoom.scale === 1) {
        gesture.originX = 0;
        gesture.originY = 0;
      }
      gesture.imageWrapEl.style.transitionDuration = '300ms';
      gesture.imageWrapEl.style.transform = `translate3d(${translateX}px, ${translateY}px,0)`;
      gesture.imageEl.style.transitionDuration = '300ms';
      gesture.imageEl.style.transform = `translate3d(0,0,0) scale(${zoom.scale})`;
    }
    function zoomOut() {
      const zoom = spswiper.zoom;
      const params = spswiper.params.zoom;
      if (!gesture.slideEl) {
        if (spswiper.params.virtual && spswiper.params.virtual.enabled && spswiper.virtual) {
          gesture.slideEl = elementChildren(spswiper.slidesEl, `.${spswiper.params.slideActiveClass}`)[0];
        } else {
          gesture.slideEl = spswiper.slides[spswiper.activeIndex];
        }
        let imageEl = gesture.slideEl.querySelector(`.${params.containerClass}`);
        if (imageEl) {
          imageEl = imageEl.querySelectorAll('picture, img, svg, canvas, .spswiper-zoom-target')[0];
        }
        gesture.imageEl = imageEl;
        if (imageEl) {
          gesture.imageWrapEl = elementParents(gesture.imageEl, `.${params.containerClass}`)[0];
        } else {
          gesture.imageWrapEl = undefined;
        }
      }
      if (!gesture.imageEl || !gesture.imageWrapEl) return;
      if (spswiper.params.cssMode) {
        spswiper.wrapperEl.style.overflow = '';
        spswiper.wrapperEl.style.touchAction = '';
      }
      zoom.scale = 1;
      currentScale = 1;
      gesture.imageWrapEl.style.transitionDuration = '300ms';
      gesture.imageWrapEl.style.transform = 'translate3d(0,0,0)';
      gesture.imageEl.style.transitionDuration = '300ms';
      gesture.imageEl.style.transform = 'translate3d(0,0,0) scale(1)';
      gesture.slideEl.classList.remove(`${params.zoomedSlideClass}`);
      gesture.slideEl = undefined;
      gesture.originX = 0;
      gesture.originY = 0;
    }

    // Toggle Zoom
    function zoomToggle(e) {
      const zoom = spswiper.zoom;
      if (zoom.scale && zoom.scale !== 1) {
        // Zoom Out
        zoomOut();
      } else {
        // Zoom In
        zoomIn(e);
      }
    }
    function getListeners() {
      const passiveListener = spswiper.params.passiveListeners ? {
        passive: true,
        capture: false
      } : false;
      const activeListenerWithCapture = spswiper.params.passiveListeners ? {
        passive: false,
        capture: true
      } : true;
      return {
        passiveListener,
        activeListenerWithCapture
      };
    }

    // Attach/Detach Events
    function enable() {
      const zoom = spswiper.zoom;
      if (zoom.enabled) return;
      zoom.enabled = true;
      const {
        passiveListener,
        activeListenerWithCapture
      } = getListeners();

      // Scale image
      spswiper.wrapperEl.addEventListener('pointerdown', onGestureStart, passiveListener);
      spswiper.wrapperEl.addEventListener('pointermove', onGestureChange, activeListenerWithCapture);
      ['pointerup', 'pointercancel', 'pointerout'].forEach(eventName => {
        spswiper.wrapperEl.addEventListener(eventName, onGestureEnd, passiveListener);
      });

      // Move image
      spswiper.wrapperEl.addEventListener('pointermove', onTouchMove, activeListenerWithCapture);
    }
    function disable() {
      const zoom = spswiper.zoom;
      if (!zoom.enabled) return;
      zoom.enabled = false;
      const {
        passiveListener,
        activeListenerWithCapture
      } = getListeners();

      // Scale image
      spswiper.wrapperEl.removeEventListener('pointerdown', onGestureStart, passiveListener);
      spswiper.wrapperEl.removeEventListener('pointermove', onGestureChange, activeListenerWithCapture);
      ['pointerup', 'pointercancel', 'pointerout'].forEach(eventName => {
        spswiper.wrapperEl.removeEventListener(eventName, onGestureEnd, passiveListener);
      });

      // Move image
      spswiper.wrapperEl.removeEventListener('pointermove', onTouchMove, activeListenerWithCapture);
    }
    on('init', () => {
      if (spswiper.params.zoom.enabled) {
        enable();
      }
    });
    on('destroy', () => {
      disable();
    });
    on('touchStart', (_s, e) => {
      if (!spswiper.zoom.enabled) return;
      onTouchStart(e);
    });
    on('touchEnd', (_s, e) => {
      if (!spswiper.zoom.enabled) return;
      onTouchEnd();
    });
    on('doubleTap', (_s, e) => {
      if (!spswiper.animating && spswiper.params.zoom.enabled && spswiper.zoom.enabled && spswiper.params.zoom.toggle) {
        zoomToggle(e);
      }
    });
    on('transitionEnd', () => {
      if (spswiper.zoom.enabled && spswiper.params.zoom.enabled) {
        onTransitionEnd();
      }
    });
    on('slideChange', () => {
      if (spswiper.zoom.enabled && spswiper.params.zoom.enabled && spswiper.params.cssMode) {
        onTransitionEnd();
      }
    });
    Object.assign(spswiper.zoom, {
      enable,
      disable,
      in: zoomIn,
      out: zoomOut,
      toggle: zoomToggle
    });
  }

  /* eslint no-bitwise: ["error", { "allow": [">>"] }] */
  function Controller(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      controller: {
        control: undefined,
        inverse: false,
        by: 'slide' // or 'container'
      }
    });

    spswiper.controller = {
      control: undefined
    };
    function LinearSpline(x, y) {
      const binarySearch = function search() {
        let maxIndex;
        let minIndex;
        let guess;
        return (array, val) => {
          minIndex = -1;
          maxIndex = array.length;
          while (maxIndex - minIndex > 1) {
            guess = maxIndex + minIndex >> 1;
            if (array[guess] <= val) {
              minIndex = guess;
            } else {
              maxIndex = guess;
            }
          }
          return maxIndex;
        };
      }();
      this.x = x;
      this.y = y;
      this.lastIndex = x.length - 1;
      // Given an x value (x2), return the expected y2 value:
      // (x1,y1) is the known point before given value,
      // (x3,y3) is the known point after given value.
      let i1;
      let i3;
      this.interpolate = function interpolate(x2) {
        if (!x2) return 0;

        // Get the indexes of x1 and x3 (the array indexes before and after given x2):
        i3 = binarySearch(this.x, x2);
        i1 = i3 - 1;

        // We have our indexes i1 & i3, so we can calculate already:
        // y2 := ((x2−x1) × (y3−y1)) ÷ (x3−x1) + y1
        return (x2 - this.x[i1]) * (this.y[i3] - this.y[i1]) / (this.x[i3] - this.x[i1]) + this.y[i1];
      };
      return this;
    }
    function getInterpolateFunction(c) {
      spswiper.controller.spline = spswiper.params.loop ? new LinearSpline(spswiper.slidesGrid, c.slidesGrid) : new LinearSpline(spswiper.snapGrid, c.snapGrid);
    }
    function setTranslate(_t, byController) {
      const controlled = spswiper.controller.control;
      let multiplier;
      let controlledTranslate;
      const SPSwiper = spswiper.constructor;
      function setControlledTranslate(c) {
        if (c.destroyed) return;

        // this will create an Interpolate function based on the snapGrids
        // x is the Grid of the scrolled scroller and y will be the controlled scroller
        // it makes sense to create this only once and recall it for the interpolation
        // the function does a lot of value caching for performance
        const translate = spswiper.rtlTranslate ? -spswiper.translate : spswiper.translate;
        if (spswiper.params.controller.by === 'slide') {
          getInterpolateFunction(c);
          // i am not sure why the values have to be multiplicated this way, tried to invert the snapGrid
          // but it did not work out
          controlledTranslate = -spswiper.controller.spline.interpolate(-translate);
        }
        if (!controlledTranslate || spswiper.params.controller.by === 'container') {
          multiplier = (c.maxTranslate() - c.minTranslate()) / (spswiper.maxTranslate() - spswiper.minTranslate());
          if (Number.isNaN(multiplier) || !Number.isFinite(multiplier)) {
            multiplier = 1;
          }
          controlledTranslate = (translate - spswiper.minTranslate()) * multiplier + c.minTranslate();
        }
        if (spswiper.params.controller.inverse) {
          controlledTranslate = c.maxTranslate() - controlledTranslate;
        }
        c.updateProgress(controlledTranslate);
        c.setTranslate(controlledTranslate, spswiper);
        c.updateActiveIndex();
        c.updateSlidesClasses();
      }
      if (Array.isArray(controlled)) {
        for (let i = 0; i < controlled.length; i += 1) {
          if (controlled[i] !== byController && controlled[i] instanceof SPSwiper) {
            setControlledTranslate(controlled[i]);
          }
        }
      } else if (controlled instanceof SPSwiper && byController !== controlled) {
        setControlledTranslate(controlled);
      }
    }
    function setTransition(duration, byController) {
      const SPSwiper = spswiper.constructor;
      const controlled = spswiper.controller.control;
      let i;
      function setControlledTransition(c) {
        if (c.destroyed) return;
        c.setTransition(duration, spswiper);
        if (duration !== 0) {
          c.transitionStart();
          if (c.params.autoHeight) {
            nextTick(() => {
              c.updateAutoHeight();
            });
          }
          elementTransitionEnd(c.wrapperEl, () => {
            if (!controlled) return;
            c.transitionEnd();
          });
        }
      }
      if (Array.isArray(controlled)) {
        for (i = 0; i < controlled.length; i += 1) {
          if (controlled[i] !== byController && controlled[i] instanceof SPSwiper) {
            setControlledTransition(controlled[i]);
          }
        }
      } else if (controlled instanceof SPSwiper && byController !== controlled) {
        setControlledTransition(controlled);
      }
    }
    function removeSpline() {
      if (!spswiper.controller.control) return;
      if (spswiper.controller.spline) {
        spswiper.controller.spline = undefined;
        delete spswiper.controller.spline;
      }
    }
    on('beforeInit', () => {
      if (typeof window !== 'undefined' && (
      // eslint-disable-line
      typeof spswiper.params.controller.control === 'string' || spswiper.params.controller.control instanceof HTMLElement)) {
        const controlElement = document.querySelector(spswiper.params.controller.control);
        if (controlElement && controlElement.spswiper) {
          spswiper.controller.control = controlElement.spswiper;
        } else if (controlElement) {
          const onControllerSPSwiper = e => {
            spswiper.controller.control = e.detail[0];
            spswiper.update();
            controlElement.removeEventListener('init', onControllerSPSwiper);
          };
          controlElement.addEventListener('init', onControllerSPSwiper);
        }
        return;
      }
      spswiper.controller.control = spswiper.params.controller.control;
    });
    on('update', () => {
      removeSpline();
    });
    on('resize', () => {
      removeSpline();
    });
    on('observerUpdate', () => {
      removeSpline();
    });
    on('setTranslate', (_s, translate, byController) => {
      if (!spswiper.controller.control || spswiper.controller.control.destroyed) return;
      spswiper.controller.setTranslate(translate, byController);
    });
    on('setTransition', (_s, duration, byController) => {
      if (!spswiper.controller.control || spswiper.controller.control.destroyed) return;
      spswiper.controller.setTransition(duration, byController);
    });
    Object.assign(spswiper.controller, {
      setTranslate,
      setTransition
    });
  }

  function A11y(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      a11y: {
        enabled: true,
        notificationClass: 'spswiper-notification',
        prevSlideMessage: 'Previous slide',
        nextSlideMessage: 'Next slide',
        firstSlideMessage: 'This is the first slide',
        lastSlideMessage: 'This is the last slide',
        paginationBulletMessage: 'Go to slide {{index}}',
        slideLabelMessage: '{{index}} / {{slidesLength}}',
        containerMessage: null,
        containerRoleDescriptionMessage: null,
        itemRoleDescriptionMessage: null,
        slideRole: 'group',
        id: null
      }
    });
    spswiper.a11y = {
      clicked: false
    };
    let liveRegion = null;
    function notify(message) {
      const notification = liveRegion;
      if (notification.length === 0) return;
      notification.innerHTML = '';
      notification.innerHTML = message;
    }
    const makeElementsArray = el => (Array.isArray(el) ? el : [el]).filter(e => !!e);
    function getRandomNumber(size) {
      if (size === void 0) {
        size = 16;
      }
      const randomChar = () => Math.round(16 * Math.random()).toString(16);
      return 'x'.repeat(size).replace(/x/g, randomChar);
    }
    function makeElFocusable(el) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('tabIndex', '0');
      });
    }
    function makeElNotFocusable(el) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('tabIndex', '-1');
      });
    }
    function addElRole(el, role) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('role', role);
      });
    }
    function addElRoleDescription(el, description) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('aria-roledescription', description);
      });
    }
    function addElControls(el, controls) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('aria-controls', controls);
      });
    }
    function addElLabel(el, label) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('aria-label', label);
      });
    }
    function addElId(el, id) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('id', id);
      });
    }
    function addElLive(el, live) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('aria-live', live);
      });
    }
    function disableEl(el) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('aria-disabled', true);
      });
    }
    function enableEl(el) {
      el = makeElementsArray(el);
      el.forEach(subEl => {
        subEl.setAttribute('aria-disabled', false);
      });
    }
    function onEnterOrSpaceKey(e) {
      if (e.keyCode !== 13 && e.keyCode !== 32) return;
      const params = spswiper.params.a11y;
      const targetEl = e.target;
      if (spswiper.pagination && spswiper.pagination.el && (targetEl === spswiper.pagination.el || spswiper.pagination.el.contains(e.target))) {
        if (!e.target.matches(classesToSelector(spswiper.params.pagination.bulletClass))) return;
      }
      if (spswiper.navigation && spswiper.navigation.nextEl && targetEl === spswiper.navigation.nextEl) {
        if (!(spswiper.isEnd && !spswiper.params.loop)) {
          spswiper.slideNext();
        }
        if (spswiper.isEnd) {
          notify(params.lastSlideMessage);
        } else {
          notify(params.nextSlideMessage);
        }
      }
      if (spswiper.navigation && spswiper.navigation.prevEl && targetEl === spswiper.navigation.prevEl) {
        if (!(spswiper.isBeginning && !spswiper.params.loop)) {
          spswiper.slidePrev();
        }
        if (spswiper.isBeginning) {
          notify(params.firstSlideMessage);
        } else {
          notify(params.prevSlideMessage);
        }
      }
      if (spswiper.pagination && targetEl.matches(classesToSelector(spswiper.params.pagination.bulletClass))) {
        targetEl.click();
      }
    }
    function updateNavigation() {
      if (spswiper.params.loop || spswiper.params.rewind || !spswiper.navigation) return;
      const {
        nextEl,
        prevEl
      } = spswiper.navigation;
      if (prevEl) {
        if (spswiper.isBeginning) {
          disableEl(prevEl);
          makeElNotFocusable(prevEl);
        } else {
          enableEl(prevEl);
          makeElFocusable(prevEl);
        }
      }
      if (nextEl) {
        if (spswiper.isEnd) {
          disableEl(nextEl);
          makeElNotFocusable(nextEl);
        } else {
          enableEl(nextEl);
          makeElFocusable(nextEl);
        }
      }
    }
    function hasPagination() {
      return spswiper.pagination && spswiper.pagination.bullets && spswiper.pagination.bullets.length;
    }
    function hasClickablePagination() {
      return hasPagination() && spswiper.params.pagination.clickable;
    }
    function updatePagination() {
      const params = spswiper.params.a11y;
      if (!hasPagination()) return;
      spswiper.pagination.bullets.forEach(bulletEl => {
        if (spswiper.params.pagination.clickable) {
          makeElFocusable(bulletEl);
          if (!spswiper.params.pagination.renderBullet) {
            addElRole(bulletEl, 'button');
            addElLabel(bulletEl, params.paginationBulletMessage.replace(/\{\{index\}\}/, elementIndex(bulletEl) + 1));
          }
        }
        if (bulletEl.matches(classesToSelector(spswiper.params.pagination.bulletActiveClass))) {
          bulletEl.setAttribute('aria-current', 'true');
        } else {
          bulletEl.removeAttribute('aria-current');
        }
      });
    }
    const initNavEl = (el, wrapperId, message) => {
      makeElFocusable(el);
      if (el.tagName !== 'BUTTON') {
        addElRole(el, 'button');
        el.addEventListener('keydown', onEnterOrSpaceKey);
      }
      addElLabel(el, message);
      addElControls(el, wrapperId);
    };
    const handlePointerDown = () => {
      spswiper.a11y.clicked = true;
    };
    const handlePointerUp = () => {
      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (!spswiper.destroyed) {
            spswiper.a11y.clicked = false;
          }
        });
      });
    };
    const handleFocus = e => {
      if (spswiper.a11y.clicked) return;
      const slideEl = e.target.closest(`.${spswiper.params.slideClass}, spswiper-slide`);
      if (!slideEl || !spswiper.slides.includes(slideEl)) return;
      const isActive = spswiper.slides.indexOf(slideEl) === spswiper.activeIndex;
      const isVisible = spswiper.params.watchSlidesProgress && spswiper.visibleSlides && spswiper.visibleSlides.includes(slideEl);
      if (isActive || isVisible) return;
      if (e.sourceCapabilities && e.sourceCapabilities.firesTouchEvents) return;
      if (spswiper.isHorizontal()) {
        spswiper.el.scrollLeft = 0;
      } else {
        spswiper.el.scrollTop = 0;
      }
      spswiper.slideTo(spswiper.slides.indexOf(slideEl), 0);
    };
    const initSlides = () => {
      const params = spswiper.params.a11y;
      if (params.itemRoleDescriptionMessage) {
        addElRoleDescription(spswiper.slides, params.itemRoleDescriptionMessage);
      }
      if (params.slideRole) {
        addElRole(spswiper.slides, params.slideRole);
      }
      const slidesLength = spswiper.slides.length;
      if (params.slideLabelMessage) {
        spswiper.slides.forEach((slideEl, index) => {
          const slideIndex = spswiper.params.loop ? parseInt(slideEl.getAttribute('data-spswiper-slide-index'), 10) : index;
          const ariaLabelMessage = params.slideLabelMessage.replace(/\{\{index\}\}/, slideIndex + 1).replace(/\{\{slidesLength\}\}/, slidesLength);
          addElLabel(slideEl, ariaLabelMessage);
        });
      }
    };
    const init = () => {
      const params = spswiper.params.a11y;
      spswiper.el.append(liveRegion);

      // Container
      const containerEl = spswiper.el;
      if (params.containerRoleDescriptionMessage) {
        addElRoleDescription(containerEl, params.containerRoleDescriptionMessage);
      }
      if (params.containerMessage) {
        addElLabel(containerEl, params.containerMessage);
      }

      // Wrapper
      const wrapperEl = spswiper.wrapperEl;
      const wrapperId = params.id || wrapperEl.getAttribute('id') || `spswiper-wrapper-${getRandomNumber(16)}`;
      const live = spswiper.params.autoplay && spswiper.params.autoplay.enabled ? 'off' : 'polite';
      addElId(wrapperEl, wrapperId);
      addElLive(wrapperEl, live);

      // Slide
      initSlides();

      // Navigation
      let {
        nextEl,
        prevEl
      } = spswiper.navigation ? spswiper.navigation : {};
      nextEl = makeElementsArray(nextEl);
      prevEl = makeElementsArray(prevEl);
      if (nextEl) {
        nextEl.forEach(el => initNavEl(el, wrapperId, params.nextSlideMessage));
      }
      if (prevEl) {
        prevEl.forEach(el => initNavEl(el, wrapperId, params.prevSlideMessage));
      }

      // Pagination
      if (hasClickablePagination()) {
        const paginationEl = Array.isArray(spswiper.pagination.el) ? spswiper.pagination.el : [spswiper.pagination.el];
        paginationEl.forEach(el => {
          el.addEventListener('keydown', onEnterOrSpaceKey);
        });
      }

      // Tab focus
      spswiper.el.addEventListener('focus', handleFocus, true);
      spswiper.el.addEventListener('pointerdown', handlePointerDown, true);
      spswiper.el.addEventListener('pointerup', handlePointerUp, true);
    };
    function destroy() {
      if (liveRegion) liveRegion.remove();
      let {
        nextEl,
        prevEl
      } = spswiper.navigation ? spswiper.navigation : {};
      nextEl = makeElementsArray(nextEl);
      prevEl = makeElementsArray(prevEl);
      if (nextEl) {
        nextEl.forEach(el => el.removeEventListener('keydown', onEnterOrSpaceKey));
      }
      if (prevEl) {
        prevEl.forEach(el => el.removeEventListener('keydown', onEnterOrSpaceKey));
      }

      // Pagination
      if (hasClickablePagination()) {
        const paginationEl = Array.isArray(spswiper.pagination.el) ? spswiper.pagination.el : [spswiper.pagination.el];
        paginationEl.forEach(el => {
          el.removeEventListener('keydown', onEnterOrSpaceKey);
        });
      }

      // Tab focus
      spswiper.el.removeEventListener('focus', handleFocus, true);
      spswiper.el.removeEventListener('pointerdown', handlePointerDown, true);
      spswiper.el.removeEventListener('pointerup', handlePointerUp, true);
    }
    on('beforeInit', () => {
      liveRegion = createElement('span', spswiper.params.a11y.notificationClass);
      liveRegion.setAttribute('aria-live', 'assertive');
      liveRegion.setAttribute('aria-atomic', 'true');
    });
    on('afterInit', () => {
      if (!spswiper.params.a11y.enabled) return;
      init();
    });
    on('slidesLengthChange snapGridLengthChange slidesGridLengthChange', () => {
      if (!spswiper.params.a11y.enabled) return;
      initSlides();
    });
    on('fromEdge toEdge afterInit lock unlock', () => {
      if (!spswiper.params.a11y.enabled) return;
      updateNavigation();
    });
    on('paginationUpdate', () => {
      if (!spswiper.params.a11y.enabled) return;
      updatePagination();
    });
    on('destroy', () => {
      if (!spswiper.params.a11y.enabled) return;
      destroy();
    });
  }

  function History(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      history: {
        enabled: false,
        root: '',
        replaceState: false,
        key: 'slides',
        keepQuery: false
      }
    });
    let initialized = false;
    let paths = {};
    const slugify = text => {
      return text.toString().replace(/\s+/g, '-').replace(/[^\w-]+/g, '').replace(/--+/g, '-').replace(/^-+/, '').replace(/-+$/, '');
    };
    const getPathValues = urlOverride => {
      const window = getWindow();
      let location;
      if (urlOverride) {
        location = new URL(urlOverride);
      } else {
        location = window.location;
      }
      const pathArray = location.pathname.slice(1).split('/').filter(part => part !== '');
      const total = pathArray.length;
      const key = pathArray[total - 2];
      const value = pathArray[total - 1];
      return {
        key,
        value
      };
    };
    const setHistory = (key, index) => {
      const window = getWindow();
      if (!initialized || !spswiper.params.history.enabled) return;
      let location;
      if (spswiper.params.url) {
        location = new URL(spswiper.params.url);
      } else {
        location = window.location;
      }
      const slide = spswiper.slides[index];
      let value = slugify(slide.getAttribute('data-history'));
      if (spswiper.params.history.root.length > 0) {
        let root = spswiper.params.history.root;
        if (root[root.length - 1] === '/') root = root.slice(0, root.length - 1);
        value = `${root}/${key ? `${key}/` : ''}${value}`;
      } else if (!location.pathname.includes(key)) {
        value = `${key ? `${key}/` : ''}${value}`;
      }
      if (spswiper.params.history.keepQuery) {
        value += location.search;
      }
      const currentState = window.history.state;
      if (currentState && currentState.value === value) {
        return;
      }
      if (spswiper.params.history.replaceState) {
        window.history.replaceState({
          value
        }, null, value);
      } else {
        window.history.pushState({
          value
        }, null, value);
      }
    };
    const scrollToSlide = (speed, value, runCallbacks) => {
      if (value) {
        for (let i = 0, length = spswiper.slides.length; i < length; i += 1) {
          const slide = spswiper.slides[i];
          const slideHistory = slugify(slide.getAttribute('data-history'));
          if (slideHistory === value) {
            const index = spswiper.getSlideIndex(slide);
            spswiper.slideTo(index, speed, runCallbacks);
          }
        }
      } else {
        spswiper.slideTo(0, speed, runCallbacks);
      }
    };
    const setHistoryPopState = () => {
      paths = getPathValues(spswiper.params.url);
      scrollToSlide(spswiper.params.speed, paths.value, false);
    };
    const init = () => {
      const window = getWindow();
      if (!spswiper.params.history) return;
      if (!window.history || !window.history.pushState) {
        spswiper.params.history.enabled = false;
        spswiper.params.hashNavigation.enabled = true;
        return;
      }
      initialized = true;
      paths = getPathValues(spswiper.params.url);
      if (!paths.key && !paths.value) {
        if (!spswiper.params.history.replaceState) {
          window.addEventListener('popstate', setHistoryPopState);
        }
        return;
      }
      scrollToSlide(0, paths.value, spswiper.params.runCallbacksOnInit);
      if (!spswiper.params.history.replaceState) {
        window.addEventListener('popstate', setHistoryPopState);
      }
    };
    const destroy = () => {
      const window = getWindow();
      if (!spswiper.params.history.replaceState) {
        window.removeEventListener('popstate', setHistoryPopState);
      }
    };
    on('init', () => {
      if (spswiper.params.history.enabled) {
        init();
      }
    });
    on('destroy', () => {
      if (spswiper.params.history.enabled) {
        destroy();
      }
    });
    on('transitionEnd _freeModeNoMomentumRelease', () => {
      if (initialized) {
        setHistory(spswiper.params.history.key, spswiper.activeIndex);
      }
    });
    on('slideChange', () => {
      if (initialized && spswiper.params.cssMode) {
        setHistory(spswiper.params.history.key, spswiper.activeIndex);
      }
    });
  }

  function HashNavigation(_ref) {
    let {
      spswiper,
      extendParams,
      emit,
      on
    } = _ref;
    let initialized = false;
    const document = getDocument();
    const window = getWindow();
    extendParams({
      hashNavigation: {
        enabled: false,
        replaceState: false,
        watchState: false,
        getSlideIndex(_s, hash) {
          if (spswiper.virtual && spswiper.params.virtual.enabled) {
            const slideWithHash = spswiper.slides.filter(slideEl => slideEl.getAttribute('data-hash') === hash)[0];
            if (!slideWithHash) return 0;
            const index = parseInt(slideWithHash.getAttribute('data-spswiper-slide-index'), 10);
            return index;
          }
          return spswiper.getSlideIndex(elementChildren(spswiper.slidesEl, `.${spswiper.params.slideClass}[data-hash="${hash}"], spswiper-slide[data-hash="${hash}"]`)[0]);
        }
      }
    });
    const onHashChange = () => {
      emit('hashChange');
      const newHash = document.location.hash.replace('#', '');
      const activeSlideEl = spswiper.virtual && spswiper.params.virtual.enabled ? spswiper.slidesEl.querySelector(`[data-spswiper-slide-index="${spswiper.activeIndex}"]`) : spswiper.slides[spswiper.activeIndex];
      const activeSlideHash = activeSlideEl ? activeSlideEl.getAttribute('data-hash') : '';
      if (newHash !== activeSlideHash) {
        const newIndex = spswiper.params.hashNavigation.getSlideIndex(spswiper, newHash);
        if (typeof newIndex === 'undefined' || Number.isNaN(newIndex)) return;
        spswiper.slideTo(newIndex);
      }
    };
    const setHash = () => {
      if (!initialized || !spswiper.params.hashNavigation.enabled) return;
      const activeSlideEl = spswiper.virtual && spswiper.params.virtual.enabled ? spswiper.slidesEl.querySelector(`[data-spswiper-slide-index="${spswiper.activeIndex}"]`) : spswiper.slides[spswiper.activeIndex];
      const activeSlideHash = activeSlideEl ? activeSlideEl.getAttribute('data-hash') || activeSlideEl.getAttribute('data-history') : '';
      if (spswiper.params.hashNavigation.replaceState && window.history && window.history.replaceState) {
        window.history.replaceState(null, null, `#${activeSlideHash}` || '');
        emit('hashSet');
      } else {
        document.location.hash = activeSlideHash || '';
        emit('hashSet');
      }
    };
    const init = () => {
      if (!spswiper.params.hashNavigation.enabled || spswiper.params.history && spswiper.params.history.enabled) return;
      initialized = true;
      const hash = document.location.hash.replace('#', '');
      if (hash) {
        const speed = 0;
        const index = spswiper.params.hashNavigation.getSlideIndex(spswiper, hash);
        spswiper.slideTo(index || 0, speed, spswiper.params.runCallbacksOnInit, true);
      }
      if (spswiper.params.hashNavigation.watchState) {
        window.addEventListener('hashchange', onHashChange);
      }
    };
    const destroy = () => {
      if (spswiper.params.hashNavigation.watchState) {
        window.removeEventListener('hashchange', onHashChange);
      }
    };
    on('init', () => {
      if (spswiper.params.hashNavigation.enabled) {
        init();
      }
    });
    on('destroy', () => {
      if (spswiper.params.hashNavigation.enabled) {
        destroy();
      }
    });
    on('transitionEnd _freeModeNoMomentumRelease', () => {
      if (initialized) {
        setHash();
      }
    });
    on('slideChange', () => {
      if (initialized && spswiper.params.cssMode) {
        setHash();
      }
    });
  }

  /* eslint no-underscore-dangle: "off" */
  /* eslint no-use-before-define: "off" */
  function Autoplay(_ref) {
    let {
      spswiper,
      extendParams,
      on,
      emit,
      params
    } = _ref;
    spswiper.autoplay = {
      running: false,
      paused: false,
      timeLeft: 0
    };
    extendParams({
      autoplay: {
        enabled: false,
        delay: 3000,
        waitForTransition: true,
        disableOnInteraction: true,
        stopOnLastSlide: false,
        reverseDirection: false,
        pauseOnMouseEnter: false
      }
    });
    let timeout;
    let raf;
    let autoplayDelayTotal = params && params.autoplay ? params.autoplay.delay : 3000;
    let autoplayDelayCurrent = params && params.autoplay ? params.autoplay.delay : 3000;
    let autoplayTimeLeft;
    let autoplayStartTime = new Date().getTime;
    let wasPaused;
    let isTouched;
    let pausedByTouch;
    let touchStartTimeout;
    let slideChanged;
    let pausedByInteraction;
    function onTransitionEnd(e) {
      if (!spswiper || spswiper.destroyed || !spswiper.wrapperEl) return;
      if (e.target !== spswiper.wrapperEl) return;
      spswiper.wrapperEl.removeEventListener('transitionend', onTransitionEnd);
      resume();
    }
    const calcTimeLeft = () => {
      if (spswiper.destroyed || !spswiper.autoplay.running) return;
      if (spswiper.autoplay.paused) {
        wasPaused = true;
      } else if (wasPaused) {
        autoplayDelayCurrent = autoplayTimeLeft;
        wasPaused = false;
      }
      const timeLeft = spswiper.autoplay.paused ? autoplayTimeLeft : autoplayStartTime + autoplayDelayCurrent - new Date().getTime();
      spswiper.autoplay.timeLeft = timeLeft;
      emit('autoplayTimeLeft', timeLeft, timeLeft / autoplayDelayTotal);
      raf = requestAnimationFrame(() => {
        calcTimeLeft();
      });
    };
    const getSlideDelay = () => {
      let activeSlideEl;
      if (spswiper.virtual && spswiper.params.virtual.enabled) {
        activeSlideEl = spswiper.slides.filter(slideEl => slideEl.classList.contains('spswiper-slide-active'))[0];
      } else {
        activeSlideEl = spswiper.slides[spswiper.activeIndex];
      }
      if (!activeSlideEl) return undefined;
      const currentSlideDelay = parseInt(activeSlideEl.getAttribute('data-spswiper-autoplay'), 10);
      return currentSlideDelay;
    };
    const run = delayForce => {
      if (spswiper.destroyed || !spswiper.autoplay.running) return;
      cancelAnimationFrame(raf);
      calcTimeLeft();
      let delay = typeof delayForce === 'undefined' ? spswiper.params.autoplay.delay : delayForce;
      autoplayDelayTotal = spswiper.params.autoplay.delay;
      autoplayDelayCurrent = spswiper.params.autoplay.delay;
      const currentSlideDelay = getSlideDelay();
      if (!Number.isNaN(currentSlideDelay) && currentSlideDelay > 0 && typeof delayForce === 'undefined') {
        delay = currentSlideDelay;
        autoplayDelayTotal = currentSlideDelay;
        autoplayDelayCurrent = currentSlideDelay;
      }
      autoplayTimeLeft = delay;
      const speed = spswiper.params.speed;
      const proceed = () => {
        if (!spswiper || spswiper.destroyed) return;
        if (spswiper.params.autoplay.reverseDirection) {
          if (!spswiper.isBeginning || spswiper.params.loop || spswiper.params.rewind) {
            spswiper.slidePrev(speed, true, true);
            emit('autoplay');
          } else if (!spswiper.params.autoplay.stopOnLastSlide) {
            spswiper.slideTo(spswiper.slides.length - 1, speed, true, true);
            emit('autoplay');
          }
        } else {
          if (!spswiper.isEnd || spswiper.params.loop || spswiper.params.rewind) {
            spswiper.slideNext(speed, true, true);
            emit('autoplay');
          } else if (!spswiper.params.autoplay.stopOnLastSlide) {
            spswiper.slideTo(0, speed, true, true);
            emit('autoplay');
          }
        }
        if (spswiper.params.cssMode) {
          autoplayStartTime = new Date().getTime();
          requestAnimationFrame(() => {
            run();
          });
        }
      };
      if (delay > 0) {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
          proceed();
        }, delay);
      } else {
        requestAnimationFrame(() => {
          proceed();
        });
      }

      // eslint-disable-next-line
      return delay;
    };
    const start = () => {
      spswiper.autoplay.running = true;
      run();
      emit('autoplayStart');
    };
    const stop = () => {
      spswiper.autoplay.running = false;
      clearTimeout(timeout);
      cancelAnimationFrame(raf);
      emit('autoplayStop');
    };
    const pause = (internal, reset) => {
      if (spswiper.destroyed || !spswiper.autoplay.running) return;
      clearTimeout(timeout);
      if (!internal) {
        pausedByInteraction = true;
      }
      const proceed = () => {
        emit('autoplayPause');
        if (spswiper.params.autoplay.waitForTransition) {
          spswiper.wrapperEl.addEventListener('transitionend', onTransitionEnd);
        } else {
          resume();
        }
      };
      spswiper.autoplay.paused = true;
      if (reset) {
        if (slideChanged) {
          autoplayTimeLeft = spswiper.params.autoplay.delay;
        }
        slideChanged = false;
        proceed();
        return;
      }
      const delay = autoplayTimeLeft || spswiper.params.autoplay.delay;
      autoplayTimeLeft = delay - (new Date().getTime() - autoplayStartTime);
      if (spswiper.isEnd && autoplayTimeLeft < 0 && !spswiper.params.loop) return;
      if (autoplayTimeLeft < 0) autoplayTimeLeft = 0;
      proceed();
    };
    const resume = () => {
      if (spswiper.isEnd && autoplayTimeLeft < 0 && !spswiper.params.loop || spswiper.destroyed || !spswiper.autoplay.running) return;
      autoplayStartTime = new Date().getTime();
      if (pausedByInteraction) {
        pausedByInteraction = false;
        run(autoplayTimeLeft);
      } else {
        run();
      }
      spswiper.autoplay.paused = false;
      emit('autoplayResume');
    };
    const onVisibilityChange = () => {
      if (spswiper.destroyed || !spswiper.autoplay.running) return;
      const document = getDocument();
      if (document.visibilityState === 'hidden') {
        pausedByInteraction = true;
        pause(true);
      }
      if (document.visibilityState === 'visible') {
        resume();
      }
    };
    const onPointerEnter = e => {
      if (e.pointerType !== 'mouse') return;
      pausedByInteraction = true;
      if (spswiper.animating || spswiper.autoplay.paused) return;
      pause(true);
    };
    const onPointerLeave = e => {
      if (e.pointerType !== 'mouse') return;
      if (spswiper.autoplay.paused) {
        resume();
      }
    };
    const attachMouseEvents = () => {
      if (spswiper.params.autoplay.pauseOnMouseEnter) {
        spswiper.el.addEventListener('pointerenter', onPointerEnter);
        spswiper.el.addEventListener('pointerleave', onPointerLeave);
      }
    };
    const detachMouseEvents = () => {
      spswiper.el.removeEventListener('pointerenter', onPointerEnter);
      spswiper.el.removeEventListener('pointerleave', onPointerLeave);
    };
    const attachDocumentEvents = () => {
      const document = getDocument();
      document.addEventListener('visibilitychange', onVisibilityChange);
    };
    const detachDocumentEvents = () => {
      const document = getDocument();
      document.removeEventListener('visibilitychange', onVisibilityChange);
    };
    on('init', () => {
      if (spswiper.params.autoplay.enabled) {
        attachMouseEvents();
        attachDocumentEvents();
        autoplayStartTime = new Date().getTime();
        start();
      }
    });
    on('destroy', () => {
      detachMouseEvents();
      detachDocumentEvents();
      if (spswiper.autoplay.running) {
        stop();
      }
    });
    on('beforeTransitionStart', (_s, speed, internal) => {
      if (spswiper.destroyed || !spswiper.autoplay.running) return;
      if (internal || !spswiper.params.autoplay.disableOnInteraction) {
        pause(true, true);
      } else {
        stop();
      }
    });
    on('sliderFirstMove', () => {
      if (spswiper.destroyed || !spswiper.autoplay.running) return;
      if (spswiper.params.autoplay.disableOnInteraction) {
        stop();
        return;
      }
      isTouched = true;
      pausedByTouch = false;
      pausedByInteraction = false;
      touchStartTimeout = setTimeout(() => {
        pausedByInteraction = true;
        pausedByTouch = true;
        pause(true);
      }, 200);
    });
    on('touchEnd', () => {
      if (spswiper.destroyed || !spswiper.autoplay.running || !isTouched) return;
      clearTimeout(touchStartTimeout);
      clearTimeout(timeout);
      if (spswiper.params.autoplay.disableOnInteraction) {
        pausedByTouch = false;
        isTouched = false;
        return;
      }
      if (pausedByTouch && spswiper.params.cssMode) resume();
      pausedByTouch = false;
      isTouched = false;
    });
    on('slideChange', () => {
      if (spswiper.destroyed || !spswiper.autoplay.running) return;
      slideChanged = true;
    });
    Object.assign(spswiper.autoplay, {
      start,
      stop,
      pause,
      resume
    });
  }

  function Thumb(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      thumbs: {
        spswiper: null,
        multipleActiveThumbs: true,
        autoScrollOffset: 0,
        slideThumbActiveClass: 'spswiper-slide-thumb-active',
        thumbsContainerClass: 'spswiper-thumbs'
      }
    });
    let initialized = false;
    let spswiperCreated = false;
    spswiper.thumbs = {
      spswiper: null
    };
    function onThumbClick() {
      const thumbsSPSwiper = spswiper.thumbs.spswiper;
      if (!thumbsSPSwiper || thumbsSPSwiper.destroyed) return;
      const clickedIndex = thumbsSPSwiper.clickedIndex;
      const clickedSlide = thumbsSPSwiper.clickedSlide;
      if (clickedSlide && clickedSlide.classList.contains(spswiper.params.thumbs.slideThumbActiveClass)) return;
      if (typeof clickedIndex === 'undefined' || clickedIndex === null) return;
      let slideToIndex;
      if (thumbsSPSwiper.params.loop) {
        slideToIndex = parseInt(thumbsSPSwiper.clickedSlide.getAttribute('data-spswiper-slide-index'), 10);
      } else {
        slideToIndex = clickedIndex;
      }
      if (spswiper.params.loop) {
        spswiper.slideToLoop(slideToIndex);
      } else {
        spswiper.slideTo(slideToIndex);
      }
    }
    function init() {
      const {
        thumbs: thumbsParams
      } = spswiper.params;
      if (initialized) return false;
      initialized = true;
      const SPSwiperClass = spswiper.constructor;
      if (thumbsParams.spswiper instanceof SPSwiperClass) {
        spswiper.thumbs.spswiper = thumbsParams.spswiper;
        Object.assign(spswiper.thumbs.spswiper.originalParams, {
          watchSlidesProgress: true,
          slideToClickedSlide: false
        });
        Object.assign(spswiper.thumbs.spswiper.params, {
          watchSlidesProgress: true,
          slideToClickedSlide: false
        });
        spswiper.thumbs.spswiper.update();
      } else if (isObject(thumbsParams.spswiper)) {
        const thumbsSPSwiperParams = Object.assign({}, thumbsParams.spswiper);
        Object.assign(thumbsSPSwiperParams, {
          watchSlidesProgress: true,
          slideToClickedSlide: false
        });
        spswiper.thumbs.spswiper = new SPSwiperClass(thumbsSPSwiperParams);
        spswiperCreated = true;
      }
      spswiper.thumbs.spswiper.el.classList.add(spswiper.params.thumbs.thumbsContainerClass);
      spswiper.thumbs.spswiper.on('tap', onThumbClick);
      return true;
    }
    function update(initial) {
      const thumbsSPSwiper = spswiper.thumbs.spswiper;
      if (!thumbsSPSwiper || thumbsSPSwiper.destroyed) return;
      const slidesPerView = thumbsSPSwiper.params.slidesPerView === 'auto' ? thumbsSPSwiper.slidesPerViewDynamic() : thumbsSPSwiper.params.slidesPerView;

      // Activate thumbs
      let thumbsToActivate = 1;
      const thumbActiveClass = spswiper.params.thumbs.slideThumbActiveClass;
      if (spswiper.params.slidesPerView > 1 && !spswiper.params.centeredSlides) {
        thumbsToActivate = spswiper.params.slidesPerView;
      }
      if (!spswiper.params.thumbs.multipleActiveThumbs) {
        thumbsToActivate = 1;
      }
      thumbsToActivate = Math.floor(thumbsToActivate);
      thumbsSPSwiper.slides.forEach(slideEl => slideEl.classList.remove(thumbActiveClass));
      if (thumbsSPSwiper.params.loop || thumbsSPSwiper.params.virtual && thumbsSPSwiper.params.virtual.enabled) {
        for (let i = 0; i < thumbsToActivate; i += 1) {
          elementChildren(thumbsSPSwiper.slidesEl, `[data-spswiper-slide-index="${spswiper.realIndex + i}"]`).forEach(slideEl => {
            slideEl.classList.add(thumbActiveClass);
          });
        }
      } else {
        for (let i = 0; i < thumbsToActivate; i += 1) {
          if (thumbsSPSwiper.slides[spswiper.realIndex + i]) {
            thumbsSPSwiper.slides[spswiper.realIndex + i].classList.add(thumbActiveClass);
          }
        }
      }
      const autoScrollOffset = spswiper.params.thumbs.autoScrollOffset;
      const useOffset = autoScrollOffset && !thumbsSPSwiper.params.loop;
      if (spswiper.realIndex !== thumbsSPSwiper.realIndex || useOffset) {
        const currentThumbsIndex = thumbsSPSwiper.activeIndex;
        let newThumbsIndex;
        let direction;
        if (thumbsSPSwiper.params.loop) {
          const newThumbsSlide = thumbsSPSwiper.slides.filter(slideEl => slideEl.getAttribute('data-spswiper-slide-index') === `${spswiper.realIndex}`)[0];
          newThumbsIndex = thumbsSPSwiper.slides.indexOf(newThumbsSlide);
          direction = spswiper.activeIndex > spswiper.previousIndex ? 'next' : 'prev';
        } else {
          newThumbsIndex = spswiper.realIndex;
          direction = newThumbsIndex > spswiper.previousIndex ? 'next' : 'prev';
        }
        if (useOffset) {
          newThumbsIndex += direction === 'next' ? autoScrollOffset : -1 * autoScrollOffset;
        }
        if (thumbsSPSwiper.visibleSlidesIndexes && thumbsSPSwiper.visibleSlidesIndexes.indexOf(newThumbsIndex) < 0) {
          if (thumbsSPSwiper.params.centeredSlides) {
            if (newThumbsIndex > currentThumbsIndex) {
              newThumbsIndex = newThumbsIndex - Math.floor(slidesPerView / 2) + 1;
            } else {
              newThumbsIndex = newThumbsIndex + Math.floor(slidesPerView / 2) - 1;
            }
          } else if (newThumbsIndex > currentThumbsIndex && thumbsSPSwiper.params.slidesPerGroup === 1) ;
          thumbsSPSwiper.slideTo(newThumbsIndex, initial ? 0 : undefined);
        }
      }
    }
    on('beforeInit', () => {
      const {
        thumbs
      } = spswiper.params;
      if (!thumbs || !thumbs.spswiper) return;
      if (typeof thumbs.spswiper === 'string' || thumbs.spswiper instanceof HTMLElement) {
        const document = getDocument();
        const getThumbsElementAndInit = () => {
          const thumbsElement = typeof thumbs.spswiper === 'string' ? document.querySelector(thumbs.spswiper) : thumbs.spswiper;
          if (thumbsElement && thumbsElement.spswiper) {
            thumbs.spswiper = thumbsElement.spswiper;
            init();
            update(true);
          } else if (thumbsElement) {
            const onThumbsSPSwiper = e => {
              thumbs.spswiper = e.detail[0];
              thumbsElement.removeEventListener('init', onThumbsSPSwiper);
              init();
              update(true);
              thumbs.spswiper.update();
              spswiper.update();
            };
            thumbsElement.addEventListener('init', onThumbsSPSwiper);
          }
          return thumbsElement;
        };
        const watchForThumbsToAppear = () => {
          if (spswiper.destroyed) return;
          const thumbsElement = getThumbsElementAndInit();
          if (!thumbsElement) {
            requestAnimationFrame(watchForThumbsToAppear);
          }
        };
        requestAnimationFrame(watchForThumbsToAppear);
      } else {
        init();
        update(true);
      }
    });
    on('slideChange update resize observerUpdate', () => {
      update();
    });
    on('setTransition', (_s, duration) => {
      const thumbsSPSwiper = spswiper.thumbs.spswiper;
      if (!thumbsSPSwiper || thumbsSPSwiper.destroyed) return;
      thumbsSPSwiper.setTransition(duration);
    });
    on('beforeDestroy', () => {
      const thumbsSPSwiper = spswiper.thumbs.spswiper;
      if (!thumbsSPSwiper || thumbsSPSwiper.destroyed) return;
      if (spswiperCreated) {
        thumbsSPSwiper.destroy();
      }
    });
    Object.assign(spswiper.thumbs, {
      init,
      update
    });
  }

  function freeMode(_ref) {
    let {
      spswiper,
      extendParams,
      emit,
      once
    } = _ref;
    extendParams({
      freeMode: {
        enabled: false,
        momentum: true,
        momentumRatio: 1,
        momentumBounce: true,
        momentumBounceRatio: 1,
        momentumVelocityRatio: 1,
        sticky: false,
        minimumVelocity: 0.02
      }
    });
    function onTouchStart() {
      if (spswiper.params.cssMode) return;
      const translate = spswiper.getTranslate();
      spswiper.setTranslate(translate);
      spswiper.setTransition(0);
      spswiper.touchEventsData.velocities.length = 0;
      spswiper.freeMode.onTouchEnd({
        currentPos: spswiper.rtl ? spswiper.translate : -spswiper.translate
      });
    }
    function onTouchMove() {
      if (spswiper.params.cssMode) return;
      const {
        touchEventsData: data,
        touches
      } = spswiper;
      // Velocity
      if (data.velocities.length === 0) {
        data.velocities.push({
          position: touches[spswiper.isHorizontal() ? 'startX' : 'startY'],
          time: data.touchStartTime
        });
      }
      data.velocities.push({
        position: touches[spswiper.isHorizontal() ? 'currentX' : 'currentY'],
        time: now()
      });
    }
    function onTouchEnd(_ref2) {
      let {
        currentPos
      } = _ref2;
      if (spswiper.params.cssMode) return;
      const {
        params,
        wrapperEl,
        rtlTranslate: rtl,
        snapGrid,
        touchEventsData: data
      } = spswiper;
      // Time diff
      const touchEndTime = now();
      const timeDiff = touchEndTime - data.touchStartTime;
      if (currentPos < -spswiper.minTranslate()) {
        spswiper.slideTo(spswiper.activeIndex);
        return;
      }
      if (currentPos > -spswiper.maxTranslate()) {
        if (spswiper.slides.length < snapGrid.length) {
          spswiper.slideTo(snapGrid.length - 1);
        } else {
          spswiper.slideTo(spswiper.slides.length - 1);
        }
        return;
      }
      if (params.freeMode.momentum) {
        if (data.velocities.length > 1) {
          const lastMoveEvent = data.velocities.pop();
          const velocityEvent = data.velocities.pop();
          const distance = lastMoveEvent.position - velocityEvent.position;
          const time = lastMoveEvent.time - velocityEvent.time;
          spswiper.velocity = distance / time;
          spswiper.velocity /= 2;
          if (Math.abs(spswiper.velocity) < params.freeMode.minimumVelocity) {
            spswiper.velocity = 0;
          }
          // this implies that the user stopped moving a finger then released.
          // There would be no events with distance zero, so the last event is stale.
          if (time > 150 || now() - lastMoveEvent.time > 300) {
            spswiper.velocity = 0;
          }
        } else {
          spswiper.velocity = 0;
        }
        spswiper.velocity *= params.freeMode.momentumVelocityRatio;
        data.velocities.length = 0;
        let momentumDuration = 1000 * params.freeMode.momentumRatio;
        const momentumDistance = spswiper.velocity * momentumDuration;
        let newPosition = spswiper.translate + momentumDistance;
        if (rtl) newPosition = -newPosition;
        let doBounce = false;
        let afterBouncePosition;
        const bounceAmount = Math.abs(spswiper.velocity) * 20 * params.freeMode.momentumBounceRatio;
        let needsLoopFix;
        if (newPosition < spswiper.maxTranslate()) {
          if (params.freeMode.momentumBounce) {
            if (newPosition + spswiper.maxTranslate() < -bounceAmount) {
              newPosition = spswiper.maxTranslate() - bounceAmount;
            }
            afterBouncePosition = spswiper.maxTranslate();
            doBounce = true;
            data.allowMomentumBounce = true;
          } else {
            newPosition = spswiper.maxTranslate();
          }
          if (params.loop && params.centeredSlides) needsLoopFix = true;
        } else if (newPosition > spswiper.minTranslate()) {
          if (params.freeMode.momentumBounce) {
            if (newPosition - spswiper.minTranslate() > bounceAmount) {
              newPosition = spswiper.minTranslate() + bounceAmount;
            }
            afterBouncePosition = spswiper.minTranslate();
            doBounce = true;
            data.allowMomentumBounce = true;
          } else {
            newPosition = spswiper.minTranslate();
          }
          if (params.loop && params.centeredSlides) needsLoopFix = true;
        } else if (params.freeMode.sticky) {
          let nextSlide;
          for (let j = 0; j < snapGrid.length; j += 1) {
            if (snapGrid[j] > -newPosition) {
              nextSlide = j;
              break;
            }
          }
          if (Math.abs(snapGrid[nextSlide] - newPosition) < Math.abs(snapGrid[nextSlide - 1] - newPosition) || spswiper.swipeDirection === 'next') {
            newPosition = snapGrid[nextSlide];
          } else {
            newPosition = snapGrid[nextSlide - 1];
          }
          newPosition = -newPosition;
        }
        if (needsLoopFix) {
          once('transitionEnd', () => {
            spswiper.loopFix();
          });
        }
        // Fix duration
        if (spswiper.velocity !== 0) {
          if (rtl) {
            momentumDuration = Math.abs((-newPosition - spswiper.translate) / spswiper.velocity);
          } else {
            momentumDuration = Math.abs((newPosition - spswiper.translate) / spswiper.velocity);
          }
          if (params.freeMode.sticky) {
            // If freeMode.sticky is active and the user ends a swipe with a slow-velocity
            // event, then durations can be 20+ seconds to slide one (or zero!) slides.
            // It's easy to see this when simulating touch with mouse events. To fix this,
            // limit single-slide swipes to the default slide duration. This also has the
            // nice side effect of matching slide speed if the user stopped moving before
            // lifting finger or mouse vs. moving slowly before lifting the finger/mouse.
            // For faster swipes, also apply limits (albeit higher ones).
            const moveDistance = Math.abs((rtl ? -newPosition : newPosition) - spswiper.translate);
            const currentSlideSize = spswiper.slidesSizesGrid[spswiper.activeIndex];
            if (moveDistance < currentSlideSize) {
              momentumDuration = params.speed;
            } else if (moveDistance < 2 * currentSlideSize) {
              momentumDuration = params.speed * 1.5;
            } else {
              momentumDuration = params.speed * 2.5;
            }
          }
        } else if (params.freeMode.sticky) {
          spswiper.slideToClosest();
          return;
        }
        if (params.freeMode.momentumBounce && doBounce) {
          spswiper.updateProgress(afterBouncePosition);
          spswiper.setTransition(momentumDuration);
          spswiper.setTranslate(newPosition);
          spswiper.transitionStart(true, spswiper.swipeDirection);
          spswiper.animating = true;
          elementTransitionEnd(wrapperEl, () => {
            if (!spswiper || spswiper.destroyed || !data.allowMomentumBounce) return;
            emit('momentumBounce');
            spswiper.setTransition(params.speed);
            setTimeout(() => {
              spswiper.setTranslate(afterBouncePosition);
              elementTransitionEnd(wrapperEl, () => {
                if (!spswiper || spswiper.destroyed) return;
                spswiper.transitionEnd();
              });
            }, 0);
          });
        } else if (spswiper.velocity) {
          emit('_freeModeNoMomentumRelease');
          spswiper.updateProgress(newPosition);
          spswiper.setTransition(momentumDuration);
          spswiper.setTranslate(newPosition);
          spswiper.transitionStart(true, spswiper.swipeDirection);
          if (!spswiper.animating) {
            spswiper.animating = true;
            elementTransitionEnd(wrapperEl, () => {
              if (!spswiper || spswiper.destroyed) return;
              spswiper.transitionEnd();
            });
          }
        } else {
          spswiper.updateProgress(newPosition);
        }
        spswiper.updateActiveIndex();
        spswiper.updateSlidesClasses();
      } else if (params.freeMode.sticky) {
        spswiper.slideToClosest();
        return;
      } else if (params.freeMode) {
        emit('_freeModeNoMomentumRelease');
      }
      if (!params.freeMode.momentum || timeDiff >= params.longSwipesMs) {
        spswiper.updateProgress();
        spswiper.updateActiveIndex();
        spswiper.updateSlidesClasses();
      }
    }
    Object.assign(spswiper, {
      freeMode: {
        onTouchStart,
        onTouchMove,
        onTouchEnd
      }
    });
  }

  function Grid(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      grid: {
        rows: 1,
        fill: 'column'
      }
    });
    let slidesNumberEvenToRows;
    let slidesPerRow;
    let numFullColumns;
    let wasMultiRow;
    const getSpaceBetween = () => {
      let spaceBetween = spswiper.params.spaceBetween;
      if (typeof spaceBetween === 'string' && spaceBetween.indexOf('%') >= 0) {
        spaceBetween = parseFloat(spaceBetween.replace('%', '')) / 100 * spswiper.size;
      } else if (typeof spaceBetween === 'string') {
        spaceBetween = parseFloat(spaceBetween);
      }
      return spaceBetween;
    };
    const initSlides = slidesLength => {
      const {
        slidesPerView
      } = spswiper.params;
      const {
        rows,
        fill
      } = spswiper.params.grid;
      numFullColumns = Math.floor(slidesLength / rows);
      if (Math.floor(slidesLength / rows) === slidesLength / rows) {
        slidesNumberEvenToRows = slidesLength;
      } else {
        slidesNumberEvenToRows = Math.ceil(slidesLength / rows) * rows;
      }
      if (slidesPerView !== 'auto' && fill === 'row') {
        slidesNumberEvenToRows = Math.max(slidesNumberEvenToRows, slidesPerView * rows);
      }
      slidesPerRow = slidesNumberEvenToRows / rows;
    };
    const updateSlide = (i, slide, slidesLength, getDirectionLabel) => {
      const {
        slidesPerGroup
      } = spswiper.params;
      const spaceBetween = getSpaceBetween();
      const {
        rows,
        fill
      } = spswiper.params.grid;
      // Set slides order
      let newSlideOrderIndex;
      let column;
      let row;
      if (fill === 'row' && slidesPerGroup > 1) {
        const groupIndex = Math.floor(i / (slidesPerGroup * rows));
        const slideIndexInGroup = i - rows * slidesPerGroup * groupIndex;
        const columnsInGroup = groupIndex === 0 ? slidesPerGroup : Math.min(Math.ceil((slidesLength - groupIndex * rows * slidesPerGroup) / rows), slidesPerGroup);
        row = Math.floor(slideIndexInGroup / columnsInGroup);
        column = slideIndexInGroup - row * columnsInGroup + groupIndex * slidesPerGroup;
        newSlideOrderIndex = column + row * slidesNumberEvenToRows / rows;
        slide.style.order = newSlideOrderIndex;
      } else if (fill === 'column') {
        column = Math.floor(i / rows);
        row = i - column * rows;
        if (column > numFullColumns || column === numFullColumns && row === rows - 1) {
          row += 1;
          if (row >= rows) {
            row = 0;
            column += 1;
          }
        }
      } else {
        row = Math.floor(i / slidesPerRow);
        column = i - row * slidesPerRow;
      }
      slide.row = row;
      slide.column = column;
      slide.style[getDirectionLabel('margin-top')] = row !== 0 ? spaceBetween && `${spaceBetween}px` : '';
    };
    const updateWrapperSize = (slideSize, snapGrid, getDirectionLabel) => {
      const {
        centeredSlides,
        roundLengths
      } = spswiper.params;
      const spaceBetween = getSpaceBetween();
      const {
        rows
      } = spswiper.params.grid;
      spswiper.virtualSize = (slideSize + spaceBetween) * slidesNumberEvenToRows;
      spswiper.virtualSize = Math.ceil(spswiper.virtualSize / rows) - spaceBetween;
      spswiper.wrapperEl.style[getDirectionLabel('width')] = `${spswiper.virtualSize + spaceBetween}px`;
      if (centeredSlides) {
        const newSlidesGrid = [];
        for (let i = 0; i < snapGrid.length; i += 1) {
          let slidesGridItem = snapGrid[i];
          if (roundLengths) slidesGridItem = Math.floor(slidesGridItem);
          if (snapGrid[i] < spswiper.virtualSize + snapGrid[0]) newSlidesGrid.push(slidesGridItem);
        }
        snapGrid.splice(0, snapGrid.length);
        snapGrid.push(...newSlidesGrid);
      }
    };
    const onInit = () => {
      wasMultiRow = spswiper.params.grid && spswiper.params.grid.rows > 1;
    };
    const onUpdate = () => {
      const {
        params,
        el
      } = spswiper;
      const isMultiRow = params.grid && params.grid.rows > 1;
      if (wasMultiRow && !isMultiRow) {
        el.classList.remove(`${params.containerModifierClass}grid`, `${params.containerModifierClass}grid-column`);
        numFullColumns = 1;
        spswiper.emitContainerClasses();
      } else if (!wasMultiRow && isMultiRow) {
        el.classList.add(`${params.containerModifierClass}grid`);
        if (params.grid.fill === 'column') {
          el.classList.add(`${params.containerModifierClass}grid-column`);
        }
        spswiper.emitContainerClasses();
      }
      wasMultiRow = isMultiRow;
    };
    on('init', onInit);
    on('update', onUpdate);
    spswiper.grid = {
      initSlides,
      updateSlide,
      updateWrapperSize
    };
  }

  function appendSlide(slides) {
    const spswiper = this;
    const {
      params,
      slidesEl
    } = spswiper;
    if (params.loop) {
      spswiper.loopDestroy();
    }
    const appendElement = slideEl => {
      if (typeof slideEl === 'string') {
        const tempDOM = document.createElement('div');
        tempDOM.innerHTML = slideEl;
        slidesEl.append(tempDOM.children[0]);
        tempDOM.innerHTML = '';
      } else {
        slidesEl.append(slideEl);
      }
    };
    if (typeof slides === 'object' && 'length' in slides) {
      for (let i = 0; i < slides.length; i += 1) {
        if (slides[i]) appendElement(slides[i]);
      }
    } else {
      appendElement(slides);
    }
    spswiper.recalcSlides();
    if (params.loop) {
      spswiper.loopCreate();
    }
    if (!params.observer || spswiper.isElement) {
      spswiper.update();
    }
  }

  function prependSlide(slides) {
    const spswiper = this;
    const {
      params,
      activeIndex,
      slidesEl
    } = spswiper;
    if (params.loop) {
      spswiper.loopDestroy();
    }
    let newActiveIndex = activeIndex + 1;
    const prependElement = slideEl => {
      if (typeof slideEl === 'string') {
        const tempDOM = document.createElement('div');
        tempDOM.innerHTML = slideEl;
        slidesEl.prepend(tempDOM.children[0]);
        tempDOM.innerHTML = '';
      } else {
        slidesEl.prepend(slideEl);
      }
    };
    if (typeof slides === 'object' && 'length' in slides) {
      for (let i = 0; i < slides.length; i += 1) {
        if (slides[i]) prependElement(slides[i]);
      }
      newActiveIndex = activeIndex + slides.length;
    } else {
      prependElement(slides);
    }
    spswiper.recalcSlides();
    if (params.loop) {
      spswiper.loopCreate();
    }
    if (!params.observer || spswiper.isElement) {
      spswiper.update();
    }
    spswiper.slideTo(newActiveIndex, 0, false);
  }

  function addSlide(index, slides) {
    const spswiper = this;
    const {
      params,
      activeIndex,
      slidesEl
    } = spswiper;
    let activeIndexBuffer = activeIndex;
    if (params.loop) {
      activeIndexBuffer -= spswiper.loopedSlides;
      spswiper.loopDestroy();
      spswiper.recalcSlides();
    }
    const baseLength = spswiper.slides.length;
    if (index <= 0) {
      spswiper.prependSlide(slides);
      return;
    }
    if (index >= baseLength) {
      spswiper.appendSlide(slides);
      return;
    }
    let newActiveIndex = activeIndexBuffer > index ? activeIndexBuffer + 1 : activeIndexBuffer;
    const slidesBuffer = [];
    for (let i = baseLength - 1; i >= index; i -= 1) {
      const currentSlide = spswiper.slides[i];
      currentSlide.remove();
      slidesBuffer.unshift(currentSlide);
    }
    if (typeof slides === 'object' && 'length' in slides) {
      for (let i = 0; i < slides.length; i += 1) {
        if (slides[i]) slidesEl.append(slides[i]);
      }
      newActiveIndex = activeIndexBuffer > index ? activeIndexBuffer + slides.length : activeIndexBuffer;
    } else {
      slidesEl.append(slides);
    }
    for (let i = 0; i < slidesBuffer.length; i += 1) {
      slidesEl.append(slidesBuffer[i]);
    }
    spswiper.recalcSlides();
    if (params.loop) {
      spswiper.loopCreate();
    }
    if (!params.observer || spswiper.isElement) {
      spswiper.update();
    }
    if (params.loop) {
      spswiper.slideTo(newActiveIndex + spswiper.loopedSlides, 0, false);
    } else {
      spswiper.slideTo(newActiveIndex, 0, false);
    }
  }

  function removeSlide(slidesIndexes) {
    const spswiper = this;
    const {
      params,
      activeIndex
    } = spswiper;
    let activeIndexBuffer = activeIndex;
    if (params.loop) {
      activeIndexBuffer -= spswiper.loopedSlides;
      spswiper.loopDestroy();
    }
    let newActiveIndex = activeIndexBuffer;
    let indexToRemove;
    if (typeof slidesIndexes === 'object' && 'length' in slidesIndexes) {
      for (let i = 0; i < slidesIndexes.length; i += 1) {
        indexToRemove = slidesIndexes[i];
        if (spswiper.slides[indexToRemove]) spswiper.slides[indexToRemove].remove();
        if (indexToRemove < newActiveIndex) newActiveIndex -= 1;
      }
      newActiveIndex = Math.max(newActiveIndex, 0);
    } else {
      indexToRemove = slidesIndexes;
      if (spswiper.slides[indexToRemove]) spswiper.slides[indexToRemove].remove();
      if (indexToRemove < newActiveIndex) newActiveIndex -= 1;
      newActiveIndex = Math.max(newActiveIndex, 0);
    }
    spswiper.recalcSlides();
    if (params.loop) {
      spswiper.loopCreate();
    }
    if (!params.observer || spswiper.isElement) {
      spswiper.update();
    }
    if (params.loop) {
      spswiper.slideTo(newActiveIndex + spswiper.loopedSlides, 0, false);
    } else {
      spswiper.slideTo(newActiveIndex, 0, false);
    }
  }

  function removeAllSlides() {
    const spswiper = this;
    const slidesIndexes = [];
    for (let i = 0; i < spswiper.slides.length; i += 1) {
      slidesIndexes.push(i);
    }
    spswiper.removeSlide(slidesIndexes);
  }

  function Manipulation(_ref) {
    let {
      spswiper
    } = _ref;
    Object.assign(spswiper, {
      appendSlide: appendSlide.bind(spswiper),
      prependSlide: prependSlide.bind(spswiper),
      addSlide: addSlide.bind(spswiper),
      removeSlide: removeSlide.bind(spswiper),
      removeAllSlides: removeAllSlides.bind(spswiper)
    });
  }

  function effectInit(params) {
    const {
      effect,
      spswiper,
      on,
      setTranslate,
      setTransition,
      overwriteParams,
      perspective,
      recreateShadows,
      getEffectParams
    } = params;
    on('beforeInit', () => {
      if (spswiper.params.effect !== effect) return;
      spswiper.classNames.push(`${spswiper.params.containerModifierClass}${effect}`);
      if (perspective && perspective()) {
        spswiper.classNames.push(`${spswiper.params.containerModifierClass}3d`);
      }
      const overwriteParamsResult = overwriteParams ? overwriteParams() : {};
      Object.assign(spswiper.params, overwriteParamsResult);
      Object.assign(spswiper.originalParams, overwriteParamsResult);
    });
    on('setTranslate', () => {
      if (spswiper.params.effect !== effect) return;
      setTranslate();
    });
    on('setTransition', (_s, duration) => {
      if (spswiper.params.effect !== effect) return;
      setTransition(duration);
    });
    on('transitionEnd', () => {
      if (spswiper.params.effect !== effect) return;
      if (recreateShadows) {
        if (!getEffectParams || !getEffectParams().slideShadows) return;
        // remove shadows
        spswiper.slides.forEach(slideEl => {
          slideEl.querySelectorAll('.spswiper-slide-shadow-top, .spswiper-slide-shadow-right, .spswiper-slide-shadow-bottom, .spswiper-slide-shadow-left').forEach(shadowEl => shadowEl.remove());
        });
        // create new one
        recreateShadows();
      }
    });
    let requireUpdateOnVirtual;
    on('virtualUpdate', () => {
      if (spswiper.params.effect !== effect) return;
      if (!spswiper.slides.length) {
        requireUpdateOnVirtual = true;
      }
      requestAnimationFrame(() => {
        if (requireUpdateOnVirtual && spswiper.slides && spswiper.slides.length) {
          setTranslate();
          requireUpdateOnVirtual = false;
        }
      });
    });
  }

  function effectTarget(effectParams, slideEl) {
    const transformEl = getSlideTransformEl(slideEl);
    if (transformEl !== slideEl) {
      transformEl.style.backfaceVisibility = 'hidden';
      transformEl.style['-webkit-backface-visibility'] = 'hidden';
    }
    return transformEl;
  }

  function effectVirtualTransitionEnd(_ref) {
    let {
      spswiper,
      duration,
      transformElements,
      allSlides
    } = _ref;
    const {
      activeIndex
    } = spswiper;
    const getSlide = el => {
      if (!el.parentElement) {
        // assume shadow root
        const slide = spswiper.slides.filter(slideEl => slideEl.shadowRoot && slideEl.shadowRoot === el.parentNode)[0];
        return slide;
      }
      return el.parentElement;
    };
    if (spswiper.params.virtualTranslate && duration !== 0) {
      let eventTriggered = false;
      let transitionEndTarget;
      if (allSlides) {
        transitionEndTarget = transformElements;
      } else {
        transitionEndTarget = transformElements.filter(transformEl => {
          const el = transformEl.classList.contains('spswiper-slide-transform') ? getSlide(transformEl) : transformEl;
          return spswiper.getSlideIndex(el) === activeIndex;
        });
      }
      transitionEndTarget.forEach(el => {
        elementTransitionEnd(el, () => {
          if (eventTriggered) return;
          if (!spswiper || spswiper.destroyed) return;
          eventTriggered = true;
          spswiper.animating = false;
          const evt = new window.CustomEvent('transitionend', {
            bubbles: true,
            cancelable: true
          });
          spswiper.wrapperEl.dispatchEvent(evt);
        });
      });
    }
  }

  function EffectFade(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      fadeEffect: {
        crossFade: false
      }
    });
    const setTranslate = () => {
      const {
        slides
      } = spswiper;
      const params = spswiper.params.fadeEffect;
      for (let i = 0; i < slides.length; i += 1) {
        const slideEl = spswiper.slides[i];
        const offset = slideEl.spswiperSlideOffset;
        let tx = -offset;
        if (!spswiper.params.virtualTranslate) tx -= spswiper.translate;
        let ty = 0;
        if (!spswiper.isHorizontal()) {
          ty = tx;
          tx = 0;
        }
        const slideOpacity = spswiper.params.fadeEffect.crossFade ? Math.max(1 - Math.abs(slideEl.progress), 0) : 1 + Math.min(Math.max(slideEl.progress, -1), 0);
        const targetEl = effectTarget(params, slideEl);
        targetEl.style.opacity = slideOpacity;
        targetEl.style.transform = `translate3d(${tx}px, ${ty}px, 0px)`;
      }
    };
    const setTransition = duration => {
      const transformElements = spswiper.slides.map(slideEl => getSlideTransformEl(slideEl));
      transformElements.forEach(el => {
        el.style.transitionDuration = `${duration}ms`;
      });
      effectVirtualTransitionEnd({
        spswiper,
        duration,
        transformElements,
        allSlides: true
      });
    };
    effectInit({
      effect: 'fade',
      spswiper,
      on,
      setTranslate,
      setTransition,
      overwriteParams: () => ({
        slidesPerView: 1,
        slidesPerGroup: 1,
        watchSlidesProgress: true,
        spaceBetween: 0,
        virtualTranslate: !spswiper.params.cssMode
      })
    });
  }

  function EffectCube(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      cubeEffect: {
        slideShadows: true,
        shadow: true,
        shadowOffset: 20,
        shadowScale: 0.94
      }
    });
    const createSlideShadows = (slideEl, progress, isHorizontal) => {
      let shadowBefore = isHorizontal ? slideEl.querySelector('.spswiper-slide-shadow-left') : slideEl.querySelector('.spswiper-slide-shadow-top');
      let shadowAfter = isHorizontal ? slideEl.querySelector('.spswiper-slide-shadow-right') : slideEl.querySelector('.spswiper-slide-shadow-bottom');
      if (!shadowBefore) {
        shadowBefore = createElement('div', `spswiper-slide-shadow-cube spswiper-slide-shadow-${isHorizontal ? 'left' : 'top'}`.split(' '));
        slideEl.append(shadowBefore);
      }
      if (!shadowAfter) {
        shadowAfter = createElement('div', `spswiper-slide-shadow-cube spswiper-slide-shadow-${isHorizontal ? 'right' : 'bottom'}`.split(' '));
        slideEl.append(shadowAfter);
      }
      if (shadowBefore) shadowBefore.style.opacity = Math.max(-progress, 0);
      if (shadowAfter) shadowAfter.style.opacity = Math.max(progress, 0);
    };
    const recreateShadows = () => {
      // create new ones
      const isHorizontal = spswiper.isHorizontal();
      spswiper.slides.forEach(slideEl => {
        const progress = Math.max(Math.min(slideEl.progress, 1), -1);
        createSlideShadows(slideEl, progress, isHorizontal);
      });
    };
    const setTranslate = () => {
      const {
        el,
        wrapperEl,
        slides,
        width: spswiperWidth,
        height: spswiperHeight,
        rtlTranslate: rtl,
        size: spswiperSize,
        browser
      } = spswiper;
      const params = spswiper.params.cubeEffect;
      const isHorizontal = spswiper.isHorizontal();
      const isVirtual = spswiper.virtual && spswiper.params.virtual.enabled;
      let wrapperRotate = 0;
      let cubeShadowEl;
      if (params.shadow) {
        if (isHorizontal) {
          cubeShadowEl = spswiper.wrapperEl.querySelector('.spswiper-cube-shadow');
          if (!cubeShadowEl) {
            cubeShadowEl = createElement('div', 'spswiper-cube-shadow');
            spswiper.wrapperEl.append(cubeShadowEl);
          }
          cubeShadowEl.style.height = `${spswiperWidth}px`;
        } else {
          cubeShadowEl = el.querySelector('.spswiper-cube-shadow');
          if (!cubeShadowEl) {
            cubeShadowEl = createElement('div', 'spswiper-cube-shadow');
            el.append(cubeShadowEl);
          }
        }
      }
      for (let i = 0; i < slides.length; i += 1) {
        const slideEl = slides[i];
        let slideIndex = i;
        if (isVirtual) {
          slideIndex = parseInt(slideEl.getAttribute('data-spswiper-slide-index'), 10);
        }
        let slideAngle = slideIndex * 90;
        let round = Math.floor(slideAngle / 360);
        if (rtl) {
          slideAngle = -slideAngle;
          round = Math.floor(-slideAngle / 360);
        }
        const progress = Math.max(Math.min(slideEl.progress, 1), -1);
        let tx = 0;
        let ty = 0;
        let tz = 0;
        if (slideIndex % 4 === 0) {
          tx = -round * 4 * spswiperSize;
          tz = 0;
        } else if ((slideIndex - 1) % 4 === 0) {
          tx = 0;
          tz = -round * 4 * spswiperSize;
        } else if ((slideIndex - 2) % 4 === 0) {
          tx = spswiperSize + round * 4 * spswiperSize;
          tz = spswiperSize;
        } else if ((slideIndex - 3) % 4 === 0) {
          tx = -spswiperSize;
          tz = 3 * spswiperSize + spswiperSize * 4 * round;
        }
        if (rtl) {
          tx = -tx;
        }
        if (!isHorizontal) {
          ty = tx;
          tx = 0;
        }
        const transform = `rotateX(${isHorizontal ? 0 : -slideAngle}deg) rotateY(${isHorizontal ? slideAngle : 0}deg) translate3d(${tx}px, ${ty}px, ${tz}px)`;
        if (progress <= 1 && progress > -1) {
          wrapperRotate = slideIndex * 90 + progress * 90;
          if (rtl) wrapperRotate = -slideIndex * 90 - progress * 90;
        }
        slideEl.style.transform = transform;
        if (params.slideShadows) {
          createSlideShadows(slideEl, progress, isHorizontal);
        }
      }
      wrapperEl.style.transformOrigin = `50% 50% -${spswiperSize / 2}px`;
      wrapperEl.style['-webkit-transform-origin'] = `50% 50% -${spswiperSize / 2}px`;
      if (params.shadow) {
        if (isHorizontal) {
          cubeShadowEl.style.transform = `translate3d(0px, ${spswiperWidth / 2 + params.shadowOffset}px, ${-spswiperWidth / 2}px) rotateX(90deg) rotateZ(0deg) scale(${params.shadowScale})`;
        } else {
          const shadowAngle = Math.abs(wrapperRotate) - Math.floor(Math.abs(wrapperRotate) / 90) * 90;
          const multiplier = 1.5 - (Math.sin(shadowAngle * 2 * Math.PI / 360) / 2 + Math.cos(shadowAngle * 2 * Math.PI / 360) / 2);
          const scale1 = params.shadowScale;
          const scale2 = params.shadowScale / multiplier;
          const offset = params.shadowOffset;
          cubeShadowEl.style.transform = `scale3d(${scale1}, 1, ${scale2}) translate3d(0px, ${spswiperHeight / 2 + offset}px, ${-spswiperHeight / 2 / scale2}px) rotateX(-90deg)`;
        }
      }
      const zFactor = (browser.isSafari || browser.isWebView) && browser.needPerspectiveFix ? -spswiperSize / 2 : 0;
      wrapperEl.style.transform = `translate3d(0px,0,${zFactor}px) rotateX(${spswiper.isHorizontal() ? 0 : wrapperRotate}deg) rotateY(${spswiper.isHorizontal() ? -wrapperRotate : 0}deg)`;
      wrapperEl.style.setProperty('--spswiper-cube-translate-z', `${zFactor}px`);
    };
    const setTransition = duration => {
      const {
        el,
        slides
      } = spswiper;
      slides.forEach(slideEl => {
        slideEl.style.transitionDuration = `${duration}ms`;
        slideEl.querySelectorAll('.spswiper-slide-shadow-top, .spswiper-slide-shadow-right, .spswiper-slide-shadow-bottom, .spswiper-slide-shadow-left').forEach(subEl => {
          subEl.style.transitionDuration = `${duration}ms`;
        });
      });
      if (spswiper.params.cubeEffect.shadow && !spswiper.isHorizontal()) {
        const shadowEl = el.querySelector('.spswiper-cube-shadow');
        if (shadowEl) shadowEl.style.transitionDuration = `${duration}ms`;
      }
    };
    effectInit({
      effect: 'cube',
      spswiper,
      on,
      setTranslate,
      setTransition,
      recreateShadows,
      getEffectParams: () => spswiper.params.cubeEffect,
      perspective: () => true,
      overwriteParams: () => ({
        slidesPerView: 1,
        slidesPerGroup: 1,
        watchSlidesProgress: true,
        resistanceRatio: 0,
        spaceBetween: 0,
        centeredSlides: false,
        virtualTranslate: true
      })
    });
  }

  function createShadow(suffix, slideEl, side) {
    const shadowClass = `spswiper-slide-shadow${side ? `-${side}` : ''}${suffix ? ` spswiper-slide-shadow-${suffix}` : ''}`;
    const shadowContainer = getSlideTransformEl(slideEl);
    let shadowEl = shadowContainer.querySelector(`.${shadowClass.split(' ').join('.')}`);
    if (!shadowEl) {
      shadowEl = createElement('div', shadowClass.split(' '));
      shadowContainer.append(shadowEl);
    }
    return shadowEl;
  }

  function EffectFlip(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      flipEffect: {
        slideShadows: true,
        limitRotation: true
      }
    });
    const createSlideShadows = (slideEl, progress) => {
      let shadowBefore = spswiper.isHorizontal() ? slideEl.querySelector('.spswiper-slide-shadow-left') : slideEl.querySelector('.spswiper-slide-shadow-top');
      let shadowAfter = spswiper.isHorizontal() ? slideEl.querySelector('.spswiper-slide-shadow-right') : slideEl.querySelector('.spswiper-slide-shadow-bottom');
      if (!shadowBefore) {
        shadowBefore = createShadow('flip', slideEl, spswiper.isHorizontal() ? 'left' : 'top');
      }
      if (!shadowAfter) {
        shadowAfter = createShadow('flip', slideEl, spswiper.isHorizontal() ? 'right' : 'bottom');
      }
      if (shadowBefore) shadowBefore.style.opacity = Math.max(-progress, 0);
      if (shadowAfter) shadowAfter.style.opacity = Math.max(progress, 0);
    };
    const recreateShadows = () => {
      // Set shadows
      spswiper.params.flipEffect;
      spswiper.slides.forEach(slideEl => {
        let progress = slideEl.progress;
        if (spswiper.params.flipEffect.limitRotation) {
          progress = Math.max(Math.min(slideEl.progress, 1), -1);
        }
        createSlideShadows(slideEl, progress);
      });
    };
    const setTranslate = () => {
      const {
        slides,
        rtlTranslate: rtl
      } = spswiper;
      const params = spswiper.params.flipEffect;
      for (let i = 0; i < slides.length; i += 1) {
        const slideEl = slides[i];
        let progress = slideEl.progress;
        if (spswiper.params.flipEffect.limitRotation) {
          progress = Math.max(Math.min(slideEl.progress, 1), -1);
        }
        const offset = slideEl.spswiperSlideOffset;
        const rotate = -180 * progress;
        let rotateY = rotate;
        let rotateX = 0;
        let tx = spswiper.params.cssMode ? -offset - spswiper.translate : -offset;
        let ty = 0;
        if (!spswiper.isHorizontal()) {
          ty = tx;
          tx = 0;
          rotateX = -rotateY;
          rotateY = 0;
        } else if (rtl) {
          rotateY = -rotateY;
        }
        slideEl.style.zIndex = -Math.abs(Math.round(progress)) + slides.length;
        if (params.slideShadows) {
          createSlideShadows(slideEl, progress);
        }
        const transform = `translate3d(${tx}px, ${ty}px, 0px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
        const targetEl = effectTarget(params, slideEl);
        targetEl.style.transform = transform;
      }
    };
    const setTransition = duration => {
      const transformElements = spswiper.slides.map(slideEl => getSlideTransformEl(slideEl));
      transformElements.forEach(el => {
        el.style.transitionDuration = `${duration}ms`;
        el.querySelectorAll('.spswiper-slide-shadow-top, .spswiper-slide-shadow-right, .spswiper-slide-shadow-bottom, .spswiper-slide-shadow-left').forEach(shadowEl => {
          shadowEl.style.transitionDuration = `${duration}ms`;
        });
      });
      effectVirtualTransitionEnd({
        spswiper,
        duration,
        transformElements
      });
    };
    effectInit({
      effect: 'flip',
      spswiper,
      on,
      setTranslate,
      setTransition,
      recreateShadows,
      getEffectParams: () => spswiper.params.flipEffect,
      perspective: () => true,
      overwriteParams: () => ({
        slidesPerView: 1,
        slidesPerGroup: 1,
        watchSlidesProgress: true,
        spaceBetween: 0,
        virtualTranslate: !spswiper.params.cssMode
      })
    });
  }

  function EffectCoverflow(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      coverflowEffect: {
        rotate: 50,
        stretch: 0,
        depth: 100,
        scale: 1,
        modifier: 1,
        slideShadows: true
      }
    });
    const setTranslate = () => {
      const {
        width: spswiperWidth,
        height: spswiperHeight,
        slides,
        slidesSizesGrid
      } = spswiper;
      const params = spswiper.params.coverflowEffect;
      const isHorizontal = spswiper.isHorizontal();
      const transform = spswiper.translate;
      const center = isHorizontal ? -transform + spswiperWidth / 2 : -transform + spswiperHeight / 2;
      const rotate = isHorizontal ? params.rotate : -params.rotate;
      const translate = params.depth;
      // Each slide offset from center
      for (let i = 0, length = slides.length; i < length; i += 1) {
        const slideEl = slides[i];
        const slideSize = slidesSizesGrid[i];
        const slideOffset = slideEl.spswiperSlideOffset;
        const centerOffset = (center - slideOffset - slideSize / 2) / slideSize;
        const offsetMultiplier = typeof params.modifier === 'function' ? params.modifier(centerOffset) : centerOffset * params.modifier;
        let rotateY = isHorizontal ? rotate * offsetMultiplier : 0;
        let rotateX = isHorizontal ? 0 : rotate * offsetMultiplier;
        // var rotateZ = 0
        let translateZ = -translate * Math.abs(offsetMultiplier);
        let stretch = params.stretch;
        // Allow percentage to make a relative stretch for responsive sliders
        if (typeof stretch === 'string' && stretch.indexOf('%') !== -1) {
          stretch = parseFloat(params.stretch) / 100 * slideSize;
        }
        let translateY = isHorizontal ? 0 : stretch * offsetMultiplier;
        let translateX = isHorizontal ? stretch * offsetMultiplier : 0;
        let scale = 1 - (1 - params.scale) * Math.abs(offsetMultiplier);

        // Fix for ultra small values
        if (Math.abs(translateX) < 0.001) translateX = 0;
        if (Math.abs(translateY) < 0.001) translateY = 0;
        if (Math.abs(translateZ) < 0.001) translateZ = 0;
        if (Math.abs(rotateY) < 0.001) rotateY = 0;
        if (Math.abs(rotateX) < 0.001) rotateX = 0;
        if (Math.abs(scale) < 0.001) scale = 0;
        const slideTransform = `translate3d(${translateX}px,${translateY}px,${translateZ}px)  rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(${scale})`;
        const targetEl = effectTarget(params, slideEl);
        targetEl.style.transform = slideTransform;
        slideEl.style.zIndex = -Math.abs(Math.round(offsetMultiplier)) + 1;
        if (params.slideShadows) {
          // Set shadows
          let shadowBeforeEl = isHorizontal ? slideEl.querySelector('.spswiper-slide-shadow-left') : slideEl.querySelector('.spswiper-slide-shadow-top');
          let shadowAfterEl = isHorizontal ? slideEl.querySelector('.spswiper-slide-shadow-right') : slideEl.querySelector('.spswiper-slide-shadow-bottom');
          if (!shadowBeforeEl) {
            shadowBeforeEl = createShadow('coverflow', slideEl, isHorizontal ? 'left' : 'top');
          }
          if (!shadowAfterEl) {
            shadowAfterEl = createShadow('coverflow', slideEl, isHorizontal ? 'right' : 'bottom');
          }
          if (shadowBeforeEl) shadowBeforeEl.style.opacity = offsetMultiplier > 0 ? offsetMultiplier : 0;
          if (shadowAfterEl) shadowAfterEl.style.opacity = -offsetMultiplier > 0 ? -offsetMultiplier : 0;
        }
      }
    };
    const setTransition = duration => {
      const transformElements = spswiper.slides.map(slideEl => getSlideTransformEl(slideEl));
      transformElements.forEach(el => {
        el.style.transitionDuration = `${duration}ms`;
        el.querySelectorAll('.spswiper-slide-shadow-top, .spswiper-slide-shadow-right, .spswiper-slide-shadow-bottom, .spswiper-slide-shadow-left').forEach(shadowEl => {
          shadowEl.style.transitionDuration = `${duration}ms`;
        });
      });
    };
    effectInit({
      effect: 'coverflow',
      spswiper,
      on,
      setTranslate,
      setTransition,
      perspective: () => true,
      overwriteParams: () => ({
        watchSlidesProgress: true
      })
    });
  }

  function EffectCreative(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      creativeEffect: {
        limitProgress: 1,
        shadowPerProgress: false,
        progressMultiplier: 1,
        perspective: true,
        prev: {
          translate: [0, 0, 0],
          rotate: [0, 0, 0],
          opacity: 1,
          scale: 1
        },
        next: {
          translate: [0, 0, 0],
          rotate: [0, 0, 0],
          opacity: 1,
          scale: 1
        }
      }
    });
    const getTranslateValue = value => {
      if (typeof value === 'string') return value;
      return `${value}px`;
    };
    const setTranslate = () => {
      const {
        slides,
        wrapperEl,
        slidesSizesGrid
      } = spswiper;
      const params = spswiper.params.creativeEffect;
      const {
        progressMultiplier: multiplier
      } = params;
      const isCenteredSlides = spswiper.params.centeredSlides;
      if (isCenteredSlides) {
        const margin = slidesSizesGrid[0] / 2 - spswiper.params.slidesOffsetBefore || 0;
        wrapperEl.style.transform = `translateX(calc(50% - ${margin}px))`;
      }
      for (let i = 0; i < slides.length; i += 1) {
        const slideEl = slides[i];
        const slideProgress = slideEl.progress;
        const progress = Math.min(Math.max(slideEl.progress, -params.limitProgress), params.limitProgress);
        let originalProgress = progress;
        if (!isCenteredSlides) {
          originalProgress = Math.min(Math.max(slideEl.originalProgress, -params.limitProgress), params.limitProgress);
        }
        const offset = slideEl.spswiperSlideOffset;
        const t = [spswiper.params.cssMode ? -offset - spswiper.translate : -offset, 0, 0];
        const r = [0, 0, 0];
        let custom = false;
        if (!spswiper.isHorizontal()) {
          t[1] = t[0];
          t[0] = 0;
        }
        let data = {
          translate: [0, 0, 0],
          rotate: [0, 0, 0],
          scale: 1,
          opacity: 1
        };
        if (progress < 0) {
          data = params.next;
          custom = true;
        } else if (progress > 0) {
          data = params.prev;
          custom = true;
        }
        // set translate
        t.forEach((value, index) => {
          t[index] = `calc(${value}px + (${getTranslateValue(data.translate[index])} * ${Math.abs(progress * multiplier)}))`;
        });
        // set rotates
        r.forEach((value, index) => {
          r[index] = data.rotate[index] * Math.abs(progress * multiplier);
        });
        slideEl.style.zIndex = -Math.abs(Math.round(slideProgress)) + slides.length;
        const translateString = t.join(', ');
        const rotateString = `rotateX(${r[0]}deg) rotateY(${r[1]}deg) rotateZ(${r[2]}deg)`;
        const scaleString = originalProgress < 0 ? `scale(${1 + (1 - data.scale) * originalProgress * multiplier})` : `scale(${1 - (1 - data.scale) * originalProgress * multiplier})`;
        const opacityString = originalProgress < 0 ? 1 + (1 - data.opacity) * originalProgress * multiplier : 1 - (1 - data.opacity) * originalProgress * multiplier;
        const transform = `translate3d(${translateString}) ${rotateString} ${scaleString}`;

        // Set shadows
        if (custom && data.shadow || !custom) {
          let shadowEl = slideEl.querySelector('.spswiper-slide-shadow');
          if (!shadowEl && data.shadow) {
            shadowEl = createShadow('creative', slideEl);
          }
          if (shadowEl) {
            const shadowOpacity = params.shadowPerProgress ? progress * (1 / params.limitProgress) : progress;
            shadowEl.style.opacity = Math.min(Math.max(Math.abs(shadowOpacity), 0), 1);
          }
        }
        const targetEl = effectTarget(params, slideEl);
        targetEl.style.transform = transform;
        targetEl.style.opacity = opacityString;
        if (data.origin) {
          targetEl.style.transformOrigin = data.origin;
        }
      }
    };
    const setTransition = duration => {
      const transformElements = spswiper.slides.map(slideEl => getSlideTransformEl(slideEl));
      transformElements.forEach(el => {
        el.style.transitionDuration = `${duration}ms`;
        el.querySelectorAll('.spswiper-slide-shadow').forEach(shadowEl => {
          shadowEl.style.transitionDuration = `${duration}ms`;
        });
      });
      effectVirtualTransitionEnd({
        spswiper,
        duration,
        transformElements,
        allSlides: true
      });
    };
    effectInit({
      effect: 'creative',
      spswiper,
      on,
      setTranslate,
      setTransition,
      perspective: () => spswiper.params.creativeEffect.perspective,
      overwriteParams: () => ({
        watchSlidesProgress: true,
        virtualTranslate: !spswiper.params.cssMode
      })
    });
  }

  function EffectCards(_ref) {
    let {
      spswiper,
      extendParams,
      on
    } = _ref;
    extendParams({
      cardsEffect: {
        slideShadows: true,
        rotate: true,
        perSlideRotate: 2,
        perSlideOffset: 8
      }
    });
    const setTranslate = () => {
      const {
        slides,
        activeIndex,
        rtlTranslate: rtl
      } = spswiper;
      const params = spswiper.params.cardsEffect;
      const {
        startTranslate,
        isTouched
      } = spswiper.touchEventsData;
      const currentTranslate = rtl ? -spswiper.translate : spswiper.translate;
      for (let i = 0; i < slides.length; i += 1) {
        const slideEl = slides[i];
        const slideProgress = slideEl.progress;
        const progress = Math.min(Math.max(slideProgress, -4), 4);
        let offset = slideEl.spswiperSlideOffset;
        if (spswiper.params.centeredSlides && !spswiper.params.cssMode) {
          spswiper.wrapperEl.style.transform = `translateX(${spswiper.minTranslate()}px)`;
        }
        if (spswiper.params.centeredSlides && spswiper.params.cssMode) {
          offset -= slides[0].spswiperSlideOffset;
        }
        let tX = spswiper.params.cssMode ? -offset - spswiper.translate : -offset;
        let tY = 0;
        const tZ = -100 * Math.abs(progress);
        let scale = 1;
        let rotate = -params.perSlideRotate * progress;
        let tXAdd = params.perSlideOffset - Math.abs(progress) * 0.75;
        const slideIndex = spswiper.virtual && spswiper.params.virtual.enabled ? spswiper.virtual.from + i : i;
        const isSwipeToNext = (slideIndex === activeIndex || slideIndex === activeIndex - 1) && progress > 0 && progress < 1 && (isTouched || spswiper.params.cssMode) && currentTranslate < startTranslate;
        const isSwipeToPrev = (slideIndex === activeIndex || slideIndex === activeIndex + 1) && progress < 0 && progress > -1 && (isTouched || spswiper.params.cssMode) && currentTranslate > startTranslate;
        if (isSwipeToNext || isSwipeToPrev) {
          const subProgress = (1 - Math.abs((Math.abs(progress) - 0.5) / 0.5)) ** 0.5;
          rotate += -28 * progress * subProgress;
          scale += -0.5 * subProgress;
          tXAdd += 96 * subProgress;
          tY = `${-25 * subProgress * Math.abs(progress)}%`;
        }
        if (progress < 0) {
          // next
          tX = `calc(${tX}px ${rtl ? '-' : '+'} (${tXAdd * Math.abs(progress)}%))`;
        } else if (progress > 0) {
          // prev
          tX = `calc(${tX}px ${rtl ? '-' : '+'} (-${tXAdd * Math.abs(progress)}%))`;
        } else {
          tX = `${tX}px`;
        }
        if (!spswiper.isHorizontal()) {
          const prevY = tY;
          tY = tX;
          tX = prevY;
        }
        const scaleString = progress < 0 ? `${1 + (1 - scale) * progress}` : `${1 - (1 - scale) * progress}`;

        /* eslint-disable */
        const transform = `
        translate3d(${tX}, ${tY}, ${tZ}px)
        rotateZ(${params.rotate ? rtl ? -rotate : rotate : 0}deg)
        scale(${scaleString})
      `;
        /* eslint-enable */

        if (params.slideShadows) {
          // Set shadows
          let shadowEl = slideEl.querySelector('.spswiper-slide-shadow');
          if (!shadowEl) {
            shadowEl = createShadow('cards', slideEl);
          }
          if (shadowEl) shadowEl.style.opacity = Math.min(Math.max((Math.abs(progress) - 0.5) / 0.5, 0), 1);
        }
        slideEl.style.zIndex = -Math.abs(Math.round(slideProgress)) + slides.length;
        const targetEl = effectTarget(params, slideEl);
        targetEl.style.transform = transform;
      }
    };
    const setTransition = duration => {
      const transformElements = spswiper.slides.map(slideEl => getSlideTransformEl(slideEl));
      transformElements.forEach(el => {
        el.style.transitionDuration = `${duration}ms`;
        el.querySelectorAll('.spswiper-slide-shadow').forEach(shadowEl => {
          shadowEl.style.transitionDuration = `${duration}ms`;
        });
      });
      effectVirtualTransitionEnd({
        spswiper,
        duration,
        transformElements
      });
    };
    effectInit({
      effect: 'cards',
      spswiper,
      on,
      setTranslate,
      setTransition,
      perspective: () => true,
      overwriteParams: () => ({
        watchSlidesProgress: true,
        virtualTranslate: !spswiper.params.cssMode
      })
    });
  }

  /**
   * SPSwiper 10.3.1
   * Most modern mobile touch slider and framework with hardware accelerated transitions
   * https://spswiperjs.com
   *
   * Copyright 2014-2023 Vladimir Kharlampidi
   *
   * Released under the MIT License
   *
   * Released on: September 28, 2023
   */


  // SPSwiper Class
  const modules = [Virtual, Keyboard, Mousewheel, Navigation, Pagination, Scrollbar, Parallax, Zoom, Controller, A11y, History, HashNavigation, Autoplay, Thumb, freeMode, Grid, Manipulation, EffectFade, EffectCube, EffectFlip, EffectCoverflow, EffectCreative, EffectCards];
  SPSwiper.use(modules);

  return SPSwiper;

})();
