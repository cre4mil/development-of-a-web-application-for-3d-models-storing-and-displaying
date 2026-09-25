/**
 * ModelViewer — Three.js based 3D viewer used by the model page, the gallery
 * quick-view dialog, the embed page and the upload thumbnail generator.
 *
 * Libraries are fetched on demand (only the loader for the requested format),
 * models stream in with a progress bar over a blurred poster, and the render
 * loop pauses while the viewer is off-screen.
 */
(() => {
  'use strict';

  const BASE = 'https://cdn.jsdelivr.net/npm/three@0.130.0';
  const LIBS = {
    three: [`${BASE}/build/three.min.js`, 'sha384-XIeZcIwWx2i8CVKHEeXtUv7cYAaKNZEqfaxJBdCjo0PcBsE/VWGKsa2SFDKvtW5S'],
    orbit: [`${BASE}/examples/js/controls/OrbitControls.js`, 'sha384-fcwmxprR7ntks0MLHmwtgWca24P1SKyvyrYWvHvD6ciUjdZ1iqG0YVYpI0KfpwB1'],
    normals: [`${BASE}/examples/js/helpers/VertexNormalsHelper.js`, 'sha384-KS9U3o57P0V9tnNlY5pl7bKySkhd5iHM2jFF4H3tUwJhMGozcnS1WtEOFkCZxCKt'],
    GLTFLoader: [`${BASE}/examples/js/loaders/GLTFLoader.js`, 'sha384-+bpKS48ZxfAa8n4kv4SZhJNbgTIxZ0zQ1Y/dqH4hrrHViarGZaihLypNJGSGa/p6'],
    OBJLoader: [`${BASE}/examples/js/loaders/OBJLoader.js`, 'sha384-UWFC8mrevmKCZhKbJ/8/dqLrRAvHArRwJCKjwruJuXyhsebGMFsIK5zrn+R9r+fT'],
    fflate: [`${BASE}/examples/js/libs/fflate.min.js`, 'sha384-z5TPRpmZ+60mv8LqyHeCA0HTTbGjLyhJPjpEb4c4mgqrdWC1zM5Sn5pSRmknOjK8'],
    FBXLoader: [`${BASE}/examples/js/loaders/FBXLoader.js`, 'sha384-6f75FYQfp4EtgxljHW3QpNgmdz9ASfe1+O00T21f8bGabqeuS8XfHjXfjjjieGfu'],
    STLLoader: [`${BASE}/examples/js/loaders/STLLoader.js`, 'sha384-QF8EmP6pyNE+i7WmcltzC4ddzFVKDxfn5WD5gXyKTSE4SCw0R25TI+q0LUlnf7tq'],
    PLYLoader: [`${BASE}/examples/js/loaders/PLYLoader.js`, 'sha384-TRjDrMoP2Iw2zIithJ7Pm10f16V6yXxbUwTEYL5urkonr6Zr+xZ2WDOj2ONVpnSd'],
    ColladaLoader: [`${BASE}/examples/js/loaders/ColladaLoader.js`, 'sha384-Ayd5ILydqZMBb7pROfS1nXmnCTp54eGKjBstFEZVQk+GO3ReMcCTzR9Z+RJ1X1fR'],
    TDSLoader: [`${BASE}/examples/js/loaders/TDSLoader.js`, 'sha384-4wpQ8AgXEeR0Ac4yCctD9EllVESYdcfZeCJ1khJD54VCdMMklOF9kiIEh+kE8uKz'],
  };
  const FORMATS = {
    glb: { loader: 'GLTFLoader', extra: [], build: (result) => result.scene || result.scenes?.[0] },
    gltf: { loader: 'GLTFLoader', extra: [], build: (result) => result.scene || result.scenes?.[0] },
    obj: { loader: 'OBJLoader', extra: [], build: (result) => result },
    fbx: { loader: 'FBXLoader', extra: ['fflate'], build: (result) => result },
    stl: { loader: 'STLLoader', extra: [], build: (geometry) => plainMesh(geometry, false) },
    ply: { loader: 'PLYLoader', extra: [], build: (geometry) => plainMesh(geometry, true) },
    dae: { loader: 'ColladaLoader', extra: [], build: (result) => result.scene },
    '3ds': { loader: 'TDSLoader', extra: [], build: (result) => result },
  };
  const WIRE_COLOR = '#22d3ee';
  const TEXTURE_SLOTS = ['map', 'normalMap', 'metalnessMap', 'roughnessMap', 'emissiveMap', 'aoMap', 'alphaMap'];

  const gray = (value) => new THREE.Color(value, value, value);
  /** Flat-colour views of one material channel: texture when present, otherwise the scalar value. */
  const CHANNELS = {
    baseColor: (m) => ({ map: m.map || null, color: m.map ? 0xffffff : (m.color || 0xcccccc) }),
    metalness: (m) => ({ map: m.metalnessMap || null, color: m.metalnessMap ? 0xffffff : gray(m.metalness ?? 0) }),
    roughness: (m) => ({ map: m.roughnessMap || null, color: m.roughnessMap ? 0xffffff : gray(m.roughness ?? 1) }),
    emission: (m) => ({ map: m.emissiveMap || null, color: m.emissiveMap ? 0xffffff : (m.emissive || 0x000000) }),
    normalMap: (m) => ({ map: m.normalMap || null, color: m.normalMap ? 0xffffff : 0x8080ff }),
    opacity: (m) => ({ map: m.alphaMap || null, color: m.alphaMap ? 0xffffff : gray(m.opacity ?? 1) }),
    specular: (m) => ({ color: m.specular || 0x333333 }),
    vertexColor: () => ({ vertexColors: true }),
  };

  const scripts = new Map();
  const loadScript = ([src, integrity]) => {
    if (!scripts.has(src)) {
      scripts.set(src, new Promise((resolve, reject) => {
        const tag = document.createElement('script');
        tag.src = src;
        tag.integrity = integrity;
        tag.crossOrigin = 'anonymous';
        tag.onload = resolve;
        tag.onerror = () => reject(new Error('โหลดไลบรารี 3D ไม่สำเร็จ — ตรวจสอบการเชื่อมต่ออินเทอร์เน็ต'));
        document.head.append(tag);
      }));
    }
    return scripts.get(src);
  };

  const ensureLibs = async (ext) => {
    const format = FORMATS[ext];
    if (!format) throw new Error(`ยังไม่รองรับไฟล์ .${ext}`);
    await loadScript(LIBS.three);
    await Promise.all([loadScript(LIBS.orbit), loadScript(LIBS.normals)]);
    await Promise.all([format.loader, ...format.extra].map((key) => loadScript(LIBS[key])));
  };

  function plainMesh(geometry, withColors) {
    geometry.computeVertexNormals();
    return new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({
      color: 0xcccccc, metalness: 0.2, roughness: 0.6, vertexColors: withColors && geometry.hasAttribute('color'),
    }));
  }

  const compact = (n) => {
    if (n >= 1e6) return `${(n / 1e6).toFixed(1)}M`;
    return n >= 1e3 ? `${(n / 1e3).toFixed(1)}k` : String(Math.round(n));
  };
  const megabytes = (n) => `${(n / 1048576).toFixed(1)} MB`;

  class ModelViewer {
    constructor(root) {
      this.root = root;
      this.box = root.querySelector('[data-viewer-canvas]');
      this.mode = 'final';
      this.wireframe = false;
      this.normals = false;
      this.wireColor = WIRE_COLOR;
      this.singleSided = false;
      this.hd = true;
      this.model = null;
      this.originals = new Map();
      this.derived = [];
      this.overlays = [];
      this.token = 0;
      this.ready = null;
      this.visible = true;
      this.bindUi();
      root._viewer = this;
    }

    /* ---------- setup ---------- */
    async init(ext) {
      await ensureLibs(ext);
      if (this.renderer) return;
      const T = THREE;
      this.scene = new T.Scene();
      this.camera = new T.PerspectiveCamera(40, 1, 0.01, 10000);
      this.renderer = new T.WebGLRenderer({ antialias: true, alpha: true });
      this.renderer.setClearColor(0x000000, 0);
      this.renderer.outputEncoding = T.sRGBEncoding;
      this.renderer.toneMapping = T.ACESFilmicToneMapping;
      this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      this.box.append(this.renderer.domElement);
      this.buildLighting();
      this.controls = new T.OrbitControls(this.camera, this.renderer.domElement);
      this.controls.enableDamping = true;
      this.controls.addEventListener('start', () => this.root.querySelector('[data-viewer-hint]')?.classList.add('hide'));
      this.matcap = this.makeMatcap();
      this.uvTexture = this.makeUvTexture();
      new ResizeObserver(() => this.resize()).observe(this.root);
      new IntersectionObserver(([entry]) => {
        this.visible = entry.isIntersecting;
        this.syncLoop();
      }).observe(this.root);
      this.resize();
    }

    buildLighting() {
      const T = THREE;
      // A small studio scene baked into an environment map gives metals something to reflect.
      const studio = new T.Scene();
      studio.add(new T.Mesh(new T.SphereGeometry(20, 32, 16), new T.MeshBasicMaterial({ color: 0x778899, side: T.BackSide })));
      [[0, 14, 6, 0xffffff], [-14, 6, 4, 0xbfdcff], [14, 4, -6, 0xffe8cc]].forEach(([x, y, z, color]) => {
        const panel = new T.Mesh(new T.PlaneGeometry(10, 10), new T.MeshBasicMaterial({ color: new T.Color(color).multiplyScalar(6), side: T.DoubleSide }));
        panel.position.set(x, y, z);
        panel.lookAt(0, 0, 0);
        studio.add(panel);
      });
      const pmrem = new T.PMREMGenerator(this.renderer);
      this.scene.environment = pmrem.fromScene(studio).texture;
      pmrem.dispose();
      this.scene.add(new T.HemisphereLight(0xffffff, 0x445566, 0.5));
      const key = new T.DirectionalLight(0xffffff, 1.1);
      key.position.set(5, 8, 6);
      this.scene.add(key);
    }

    makeMatcap() {
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 256;
      const context = canvas.getContext('2d');
      const gradient = context.createRadialGradient(98, 98, 10, 128, 128, 126);
      gradient.addColorStop(0, '#ffffff');
      gradient.addColorStop(0.5, '#aaaaaa');
      gradient.addColorStop(1, '#222222');
      context.fillStyle = gradient;
      context.beginPath();
      context.arc(128, 128, 126, 0, Math.PI * 2);
      context.fill();
      return new THREE.CanvasTexture(canvas);
    }

    makeUvTexture() {
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 512;
      const context = canvas.getContext('2d');
      for (let y = 0; y < 8; y++) {
        for (let x = 0; x < 8; x++) {
          context.fillStyle = (x + y) % 2 ? '#e2e8f0' : `hsl(${(x * 8 + y) * 5}, 70%, 55%)`;
          context.fillRect(x * 64, y * 64, 64, 64);
        }
      }
      const texture = new THREE.CanvasTexture(canvas);
      texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
      return texture;
    }

    resize() {
      if (!this.renderer) return;
      const width = this.root.clientWidth || 640;
      const height = this.root.clientHeight || 480;
      this.renderer.setSize(width, height, false);
      this.camera.aspect = width / height;
      this.camera.updateProjectionMatrix();
    }

    syncLoop() {
      if (!this.renderer) return;
      const run = this.model && this.visible;
      this.renderer.setAnimationLoop(run ? () => {
        this.controls.update();
        this.renderer.render(this.scene, this.camera);
      } : null);
    }

    /* ---------- loading ---------- */
    setProgress(fraction, label) {
      const bar = this.root.querySelector('[data-viewer-progress]');
      if (bar) bar.style.width = `${Math.round(fraction * 100)}%`;
      const text = this.root.querySelector('[data-viewer-label]');
      if (text && label) text.textContent = label;
    }

    showPoster(show, { title, poster } = {}) {
      const element = this.root.querySelector('[data-viewer-poster]');
      element?.classList.toggle('done', !show);
      if (title !== undefined) {
        const target = this.root.querySelector('[data-viewer-title]');
        if (target) target.textContent = title;
      }
      if (poster !== undefined) {
        const image = this.root.querySelector('[data-viewer-poster-img]');
        if (image) image.src = poster;
      }
    }

    showError(message) {
      const box = this.root.querySelector('[data-viewer-error]');
      const text = this.root.querySelector('[data-viewer-error-text]');
      if (text) text.textContent = message;
      box?.classList.add('show');
      this.showPoster(false);
    }

    async load(url, ext, poster = {}) {
      const token = ++this.token;
      this.unload();
      this.root.querySelector('[data-viewer-error]')?.classList.remove('show');
      this.showPoster(true, poster);
      this.setProgress(0.02, 'กำลังเตรียมโมเดล…');
      try {
        await this.init(ext);
        const object = await this.fetchObject(url, ext, token);
        if (token !== this.token) return; // a newer load() replaced this one
        this.setModel(object);
        this.setProgress(1, 'พร้อมแล้ว');
        this.showPoster(false);
        this.root.dispatchEvent(new CustomEvent('viewer:loaded', { bubbles: true, detail: this.stats() }));
      } catch (error) {
        if (token === this.token) this.showError(error.message || 'โหลดโมเดลไม่สำเร็จ');
      }
    }

    fetchObject(url, ext, token) {
      const format = FORMATS[ext];
      const loader = new THREE[format.loader]();
      return new Promise((resolve, reject) => {
        loader.load(url, (result) => {
          const object = format.build(result);
          return object ? resolve(object) : reject(new Error('ไฟล์โมเดลไม่ถูกต้อง'));
        }, (event) => {
          if (token !== this.token) return;
          if (event.lengthComputable) {
            this.setProgress(event.loaded / event.total, `${Math.round((event.loaded / event.total) * 100)}% · ${megabytes(event.loaded)} / ${megabytes(event.total)}`);
          } else {
            this.setProgress(0.5, `กำลังดาวน์โหลด ${megabytes(event.loaded)}`);
          }
        }, () => reject(new Error('โหลดไฟล์โมเดลไม่สำเร็จ')));
      });
    }

    setModel(object) {
      this.model = object;
      this.scene.add(object);
      this.originals.clear();
      object.traverse((child) => {
        if (child.isMesh && child.material) {
          this.originals.set(child, Array.isArray(child.material) ? child.material.map((m) => m.clone()) : child.material.clone());
        }
      });
      this.resetView();
      this.applyMode();
      this.syncLoop();
      const stats = this.root.querySelector('[data-viewer-stats]');
      if (stats) {
        const s = this.stats();
        stats.innerHTML = `<span><i class="bi bi-triangle"></i> ${compact(s.triangles)}</span><span><i class="bi bi-diagram-3"></i> ${compact(s.vertices)}</span>`;
        stats.hidden = false;
      }
    }

    unload() {
      if (!this.model) return;
      this.clearOverlays();
      this.disposeDerived();
      this.scene.remove(this.model);
      this.model.traverse((child) => {
        child.geometry?.userData?.wire?.dispose();
        child.geometry?.dispose?.();
        (Array.isArray(child.material) ? child.material : [child.material]).forEach((m) => m?.dispose?.());
      });
      this.originals.forEach((value) => (Array.isArray(value) ? value : [value]).forEach((m) => m.dispose()));
      this.originals.clear();
      this.model = null;
      this.syncLoop();
      const stats = this.root.querySelector('[data-viewer-stats]');
      if (stats) stats.hidden = true;
    }

    dispose() {
      this.token++;
      this.unload();
      if (this.renderer) {
        this.renderer.setAnimationLoop(null);
        this.renderer.dispose();
        this.renderer.domElement.remove();
        this.renderer = null;
      }
    }

    /* ---------- camera ---------- */
    resetView() {
      const T = THREE;
      const box = new T.Box3().setFromObject(this.model);
      const size = box.getSize(new T.Vector3());
      const center = box.getCenter(new T.Vector3());
      const max = Math.max(size.x, size.y, size.z) || 1;
      const fov = T.MathUtils.degToRad(this.camera.fov);
      const fit = max / (2 * Math.tan(fov / 2));
      const distance = Math.max(fit, fit / this.camera.aspect) * 1.4;
      this.camera.near = distance / 100;
      this.camera.far = distance * 100;
      this.camera.updateProjectionMatrix();
      this.camera.position.copy(center).add(new T.Vector3(distance * 0.8, distance * 0.55, distance * 0.9));
      this.controls.target.copy(center);
      this.controls.update();
      this.wireSize = max;
    }

    /* ---------- statistics ---------- */
    stats() {
      let vertices = 0;
      let triangles = 0;
      let meshes = 0;
      const materials = new Set();
      const textures = new Set();
      this.model.traverse((child) => {
        if (!child.isMesh) return;
        meshes++;
        const position = child.geometry?.attributes?.position;
        if (position) {
          vertices += position.count;
          triangles += child.geometry.index ? child.geometry.index.count / 3 : position.count / 3;
        }
        [this.originals.get(child) || child.material].flat().filter(Boolean).forEach((material) => {
          materials.add(material.uuid);
          TEXTURE_SLOTS.filter((key) => material[key]).forEach((key) => textures.add(material[key].uuid));
        });
      });
      const size = new THREE.Box3().setFromObject(this.model).getSize(new THREE.Vector3());
      return { vertices, triangles: Math.round(triangles), meshes, materials: materials.size, textures: textures.size, dimensions: [size.x, size.y, size.z] };
    }

    /* ---------- inspector ---------- */
    disposeDerived() {
      this.derived.forEach((material) => material.dispose());
      this.derived = [];
    }

    /** Builds the surface material for the active inspector mode. */
    buildMaterial(original) {
      const T = THREE;
      const special = {
        final: () => original.clone(),
        noPost: () => original.clone(),
        matcap: () => new T.MeshMatcapMaterial({ matcap: this.matcap }),
        matcapSurface: () => new T.MeshMatcapMaterial({ matcap: this.matcap, normalMap: original.normalMap || null, normalScale: original.normalScale }),
        uv: () => new T.MeshBasicMaterial({ map: this.uvTexture }),
      }[this.mode];
      const channel = CHANNELS[this.mode];

      if (special) return special();
      return new T.MeshBasicMaterial(channel ? channel(original) : { color: 0xcccccc });
    }

    materialFor(original) {
      const T = THREE;
      const material = this.buildMaterial(original);
      material.side = this.singleSided ? T.FrontSide : (original.side ?? T.FrontSide);
      if (this.mode !== 'opacity' && original.transparent) {
        material.transparent = true;
        material.opacity = original.opacity;
        material.alphaTest = original.alphaTest;
      }
      material.polygonOffset = true; // keeps the wireframe overlay from z-fighting with the surface
      material.polygonOffsetFactor = material.polygonOffsetUnits = 1;
      this.derived.push(material);
      return material;
    }

    applyMode() {
      if (!this.model) return;
      this.renderer.toneMapping = this.mode === 'noPost' ? THREE.NoToneMapping : THREE.ACESFilmicToneMapping;
      this.disposeDerived();
      this.model.traverse((child) => {
        const original = this.originals.get(child);
        if (!child.isMesh || !original) return;
        child.material = Array.isArray(original) ? original.map((m) => this.materialFor(m)) : this.materialFor(original);
      });
      this.refreshOverlays();
    }

    setMode(mode) {
      this.mode = mode;
      this.root.querySelectorAll('[data-mode]').forEach((button) => button.classList.toggle('active', button.dataset.mode === mode));
      this.applyMode();
    }

    clearOverlays() {
      this.overlays.forEach(({ parent, object }) => {
        parent.remove(object);
        object.material?.dispose?.();
        object.dispose?.();
      });
      this.overlays = [];
    }

    /** Line geometry that reuses the position buffer, far cheaper than WireframeGeometry on big meshes. */
    wireGeometry(geometry) {
      if (geometry.userData.wire) return geometry.userData.wire;
      const position = geometry.getAttribute('position');
      const source = geometry.index ? geometry.index.array : null;
      const count = source ? source.length : position.count;
      const indices = new (position.count > 65535 ? Uint32Array : Uint16Array)(count * 2);
      for (let i = 0, j = 0; i < count; i += 3) {
        const [a, b, c] = source ? [source[i], source[i + 1], source[i + 2]] : [i, i + 1, i + 2];
        indices.set([a, b, b, c, c, a], j);
        j += 6;
      }
      const wire = new THREE.BufferGeometry();
      wire.setAttribute('position', position);
      wire.setIndex(new THREE.BufferAttribute(indices, 1));
      geometry.userData.wire = wire;
      return wire;
    }

    refreshOverlays() {
      this.clearOverlays();
      if (!this.model) return;
      this.model.traverse((child) => {
        if (!child.isMesh) return;
        if (this.wireframe) {
          const lines = new THREE.LineSegments(this.wireGeometry(child.geometry), new THREE.LineBasicMaterial({ color: new THREE.Color(this.wireColor), transparent: true, opacity: 0.8 }));
          child.add(lines);
          this.overlays.push({ parent: child, object: lines });
        }
        if (this.normals && THREE.VertexNormalsHelper) {
          const helper = new THREE.VertexNormalsHelper(child, (this.wireSize || 1) * 0.03, 0x00ff88);
          this.scene.add(helper);
          this.overlays.push({ parent: this.scene, object: helper });
        }
      });
    }

    /* ---------- toolbar + inspector wiring ---------- */
    bindUi() {
      const root = this.root;
      root.addEventListener('click', (event) => {
        const toolbar = event.target.closest('[data-viewer-btn]');
        if (toolbar) return this.onToolbar(toolbar.dataset.viewerBtn, toolbar);
        const mode = event.target.closest('[data-mode]');
        if (mode) return this.setMode(mode.dataset.mode);
        const overlay = event.target.closest('[data-overlay]');
        if (overlay) return this.toggleOverlay(overlay.dataset.overlay, overlay);
        const swatch = event.target.closest('[data-swatch]');
        if (swatch) return this.pickSwatch(swatch);
        return undefined;
      });
      root.querySelector('[data-viewer-single]')?.addEventListener('change', (event) => {
        this.singleSided = event.target.checked;
        this.applyMode();
      });
      root.addEventListener('dblclick', (event) => {
        if (this.model && !event.target.closest('.viewer-toolbar, .inspector, .viewer-help')) this.resetView();
      });
      document.addEventListener('fullscreenchange', () => {
        const active = document.fullscreenElement === root;
        root.querySelector('[data-viewer-btn="fullscreen"]')?.classList.toggle('active', active);
        this.resize();
      });
      const vr = root.querySelector('[data-viewer-btn="vr"]');
      navigator.xr?.isSessionSupported?.('immersive-vr').then((supported) => { if (supported && vr) vr.hidden = false; }).catch(() => {});
    }

    onToolbar(name, button) {
      switch (name) {
        case 'help': this.root.querySelector('[data-viewer-help]')?.classList.toggle('open'); button.classList.toggle('active'); break;
        case 'inspector': this.root.querySelector('[data-viewer-inspector]')?.classList.toggle('open'); break;
        case 'quality': this.toggleQuality(button); break;
        case 'rotate': this.toggleRotate(button); break;
        case 'fullscreen': (document.fullscreenElement === this.root ? document.exitFullscreen() : this.root.requestFullscreen?.())?.catch(() => {}); break;
        case 'vr': this.enterVr(); break;
        default:
      }
    }

    toggleQuality(button) {
      this.hd = !this.hd;
      button.classList.toggle('active', !this.hd);
      const label = button.querySelector('[data-viewer-quality]');
      if (label) label.textContent = this.hd ? 'HD' : 'SD';
      this.renderer?.setPixelRatio(this.hd ? Math.min(window.devicePixelRatio, 2) : 1);
      this.resize();
    }

    toggleRotate(button) {
      if (!this.controls) return;
      this.controls.autoRotate = !this.controls.autoRotate;
      this.controls.autoRotateSpeed = 2.2;
      button.classList.toggle('active', this.controls.autoRotate);
    }

    toggleOverlay(name, button) {
      if (name === 'wireframe') this.wireframe = !this.wireframe;
      if (name === 'normals') this.normals = !this.normals;
      button.classList.toggle('active', name === 'wireframe' ? this.wireframe : this.normals);
      this.refreshOverlays();
    }

    pickSwatch(swatch) {
      this.root.querySelectorAll('[data-swatch]').forEach((s) => s.classList.toggle('active', s === swatch));
      this.wireColor = swatch.dataset.swatch === 'default' ? WIRE_COLOR : swatch.dataset.swatch;
      this.wireframe = true;
      this.root.querySelector('[data-overlay="wireframe"]')?.classList.add('active');
      this.refreshOverlays();
    }

    async enterVr() {
      try {
        this.renderer.xr.enabled = true;
        const session = await navigator.xr.requestSession('immersive-vr', { optionalFeatures: ['local-floor', 'bounded-floor'] });
        await this.renderer.xr.setSession(session);
      } catch (error) {
        window.App?.toast(`เปิดโหมด VR ไม่ได้: ${error.message}`, 'danger');
      }
    }

    /* ---------- thumbnail ---------- */
    /** Renders the current view onto a 4:3 PNG (dark studio background). */
    captureThumbnail(width = 640, height = 480) {
      this.renderer.render(this.scene, this.camera);
      const canvas = document.createElement('canvas');
      canvas.width = width;
      canvas.height = height;
      const context = canvas.getContext('2d');
      const gradient = context.createRadialGradient(width / 2, height * 0.35, 20, width / 2, height / 2, width * 0.8);
      gradient.addColorStop(0, '#1e293b');
      gradient.addColorStop(1, '#0b1220');
      context.fillStyle = gradient;
      context.fillRect(0, 0, width, height);
      const source = this.renderer.domElement;
      const scale = Math.max(width / source.width, height / source.height);
      context.drawImage(source, (width - source.width * scale) / 2, (height - source.height * scale) / 2, source.width * scale, source.height * scale);
      return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
    }
  }

  /** Starts every viewer that declares data-src (model page, embed page). */
  const mountAll = () => {
    document.querySelectorAll('[data-viewer][data-src]').forEach((root) => {
      if (root.dataset.src && !root._viewer) {
        new ModelViewer(root).load(root.dataset.src, root.dataset.ext);
      }
    });
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAll);
  } else {
    mountAll();
  }

  window.ModelViewer = ModelViewer;
  ModelViewer.formatCount = compact;
})();
