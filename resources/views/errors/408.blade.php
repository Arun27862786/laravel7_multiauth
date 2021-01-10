<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/88/three.min.js"></script>
<script id="vertexShader" type="x-shader/x-vertex">
    void main() {
        gl_Position = vec4( position, 1.0 );
    }
</script>
<script id="fragmentShader" type="x-shader/x-fragment">
  uniform vec2 u_resolution;
  uniform vec2 u_mouse;
  uniform float u_time;
  uniform sampler2D u_noise;
  uniform sampler2D u_clockface;
  
  #define PI 3.141592653589793
  #define TAU 6.283185307179586

  vec2 hash2(vec2 p)
  {
    vec2 o = texture2D( u_noise, (p+0.5)/256.0, -100.0 ).xy;
    return o;
  }
  
  vec3 hsb2rgb( in vec3 c ){
    vec3 rgb = clamp(abs(mod(c.x*6.0+vec3(0.0,4.0,2.0),
                             6.0)-3.0)-1.0,
                     0.0,
                     1.0 );
    rgb = rgb*rgb*(3.0-2.0*rgb);
    return c.z * mix( vec3(1.0), rgb, c.y);
  }
  
  vec3 domain(vec2 z){
    return vec3(hsb2rgb(vec3(atan(z.y,z.x)/TAU,1.,1.)));
  }
  vec3 colour(vec2 z) {
      return domain(z);
  }
  // These awesome complex Math functions curtesy of 
  // https://github.com/mkovacs/reim/blob/master/reim.glsl
  vec2 cCis(float r);
  vec2 cLog(vec2 c); // principal value
  vec2 cInv(vec2 c);
  float cArg(vec2 c);
  float cAbs(vec2 c);
  
  vec2 cMul(vec2 a, vec2 b);
  vec2 cDiv(vec2 a, vec2 b);

  vec2 cCis(float r)
  {
    return vec2( cos(r), sin(r) );
  }
  vec2 cExp(vec2 c)
  {
    return exp(c.x) * cCis(c.y);
  }
  vec2 cConj(vec2 c)
  {
    return vec2(c.x, -c.y);
  }
  vec2 cInv(vec2 c)
  {
    return cConj(c) / dot(c, c);
  }
  vec2 cLog(vec2 c)
  {
    return vec2( log( cAbs(c) ), cArg(c) );
  }
  float cArg(vec2 c)
  {
    return atan(c.y, c.x);
  }
  float cAbs(vec2 c)
  {
    return length(c);
  }
  vec2 cMul(vec2 a, vec2 b)
  {
    return vec2(a.x*b.x - a.y*b.y, a.x*b.y + a.y*b.x);
  }
  vec2 cDiv(vec2 a, vec2 b)
  {
    return cMul(a, cInv(b));
  }
  
  // float r1 = 0.1;
  float r2 = 0.47;
  
  vec2 Droste(vec2 uv, inout float id) {
    
    float l = 1. - length(uv) * .5;
    
    // float sint = sin(u_time*.001)*.5 + .5;
    float sint = .025;
    // sint = sin(u_time*.5)*.08;
    float r1 = 0.1 + sint;
    
    // 5. Take the tiled strips back to ordinary space.
    uv = cLog(uv); 
    uv.x -= u_time;
    // 4. Scale and rotate the strips
    float scale = log(r2/r1);
    float angle = atan(scale/(2.0*PI));
    uv = cDiv(uv, cExp(vec2(0,angle))*cos(angle)); 
    // 3. this simulates zooming in the tile
    // uv -= u_time * 1.5;
    uv.y -= u_time*0.5;
    // uv.x -= u_time * .001;
    // 2. Tile the strips
    uv.x = mod(uv.x,log(r2/r1));
    id = smoothstep(.15, .0, (uv.x * l));
    // 1. Take the annulus to a strip
    uv = cExp(uv)*r1;
    
    
    return uv;
  }
  
  // Standard Mobius transform: f(z) = (az + b)/(cz + d). Slightly obfuscated.
  vec2 mobius(vec2 p, vec2 z1, vec2 z2){
    z1 = p - z1;
    p -= z2;
    return vec2(dot(z1, p), z1.y*p.x - z1.x*p.y)/dot(p, p);
  }
  
  float df_line( in vec2 a, in vec2 b, in vec2 p)
  {
    vec2 pa = p - a;
    vec2 ba = b - a;
    float h = clamp(dot(pa,ba) / dot(ba,ba), 0., 1.);	
    return length(pa - ba * h);
  }
  float line(vec2 a, vec2 b, vec2 uv) {
      float r1 = .012;
      float r2 = .01;

      float d = df_line(a, b, uv);
      // float d2 = length(a-b);
      // float fade = smoothstep(1.5, .5, d2);

      // fade += smoothstep(.05, .04, abs(d2-.75));
      return smoothstep(r1, r2, d);
  }
  
  float tri(vec2 uv) {
    uv = (uv * 2.-1.)*2.;
    return max(abs(uv.x) * 0.866025 + uv.y * 0.5, -uv.y * 0.5);
  }
  
  float arrow(vec2 a, vec2 b, vec2 uv, float w, float aa) {
    float r1 = w + aa;
    float r2 = w;
    
    float line = df_line(a, b, uv);
    
    float angle = atan(a.y - b.y, a.x - b.x);
    float c = cos(-angle+.5);
    float s = sin(-angle+.5);
    float c1 = cos(-angle+.015);
    float s1 = sin(-angle+.015);
    
    uv.x += c1*length(b);
    uv.y += -s1*length(b);
    uv *= mat2(c, -s, s, c);
    uv += .5;
    
    // uv -= b;
    // uv *= mat2(c, -s, s, c);
    
    float head = tri(uv)*.25;
    
    float d = min(head, line);
    
    // return smoothstep(.15, .1, head);
    
    return smoothstep(r1, r2, d);
  }

  void main() {
    vec2 uv = (gl_FragCoord.xy - 0.5 * u_resolution.xy) / min(u_resolution.y, u_resolution.x);
    
    vec2 z = uv;
    // float _c = cos(u_time);
    // float _s = sin(u_time);
    // z = mobius(uv, vec2(_c * .2, _s * .2), vec2(_c * -.2, _s * -.2));
    // z = mix(uv, z, sin(u_time*.2) * .5 + .5);
    
    float id;
    uv = Droste(z, id);
    id += 1.;
    
    vec4 colour = vec4(1.);
    
    colour = texture2D(u_clockface, uv+.5);
    
    float c = cos(-u_time*6.);
    float s = sin(-u_time*6.);
    
    float hands = arrow(vec2(0.), vec2(c * .28, s * .28), uv, .005, .002);
    hands += arrow(vec2(0.), vec2(c * .28, s * .28), uv + vec2(0, .02), .0001, .01) * .2;
    
    c = cos(-u_time*.5);
    s = sin(-u_time*.5);
    
    hands += arrow(vec2(0.), vec2(c * .18, s * .18), uv, .008, .002);
    hands += arrow(vec2(0.), vec2(c * .18, s * .18), uv + vec2(0, .01), .004, .01) * .2;
    
    colour = mix(colour, vec4(0.), smoothstep(0.2, .6, id * .2)*.5);
    colour = mix(colour, vec4(0.), hands);
    
    gl_FragColor = colour;
  }
</script>


<div id="container" touch-action="none"></div>

<div class="message">
  <p>408 Err'r</p>
  <p>Your time has run out</p>
  <p>Too bad.</p>
</div>
<style>
body {
  margin: 0;
  padding: 0;
}

#container {
  position: fixed;
  touch-action: none;
}

.message {
  // background: rgba(255,255,255,.8);
  padding: 0 20px;
  font-family: Helvetica, Arial;
  position: fixed;
  bottom: 0%;
  left: 50%;
  transform: translate(-50%, -50%);
  
}
p {
  margin: 2px;
}
</style>

<script>
/*
Most of the stuff in here is just bootstrapping. Essentially it's just
setting ThreeJS up so that it renders a flat surface upon which to draw 
the shader. The only thing to see here really is the uniforms sent to 
the shader. Apart from that all of the magic happens in the HTML view
under the fragment shader.
*/

let container;
let camera, scene, renderer;
let uniforms;

let loader=new THREE.TextureLoader();
let texture, clockface;
loader.setCrossOrigin("anonymous");
loader.load(
  'https://s3-us-west-2.amazonaws.com/s.cdpn.io/982762/noise.png',
  (tex) => {
    texture = tex;
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.minFilter = THREE.LinearFilter;
  loader.load(
    'https://s3-us-west-2.amazonaws.com/s.cdpn.io/982762/clockface.png',
    (tex) => {
      clockface = tex;
      clockface.wrapS = THREE.RepeatWrapping;
      clockface.wrapT = THREE.RepeatWrapping;
      init();
      animate();
    });
  }
);

function init() {
  container = document.getElementById( 'container' );

  camera = new THREE.Camera();
  camera.position.z = 1;

  scene = new THREE.Scene();

  var geometry = new THREE.PlaneBufferGeometry( 2, 2 );

  uniforms = {
    u_time: { type: "f", value: 1.0 },
    u_resolution: { type: "v2", value: new THREE.Vector2() },
    u_noise: { type: "t", value: texture },
    u_clockface: {type: "y", value: clockface },
    u_mouse: { type: "v2", value: new THREE.Vector2() }
  };

  var material = new THREE.ShaderMaterial( {
    uniforms: uniforms,
    vertexShader: document.getElementById( 'vertexShader' ).textContent,
    fragmentShader: document.getElementById( 'fragmentShader' ).textContent
  } );
  material.extensions.derivatives = true;

  var mesh = new THREE.Mesh( geometry, material );
  scene.add( mesh );

  renderer = new THREE.WebGLRenderer();
  renderer.setPixelRatio( window.devicePixelRatio );

  container.appendChild( renderer.domElement );

  onWindowResize();
  window.addEventListener( 'resize', onWindowResize, false );

  document.addEventListener('pointermove', (e)=> {
    let ratio = window.innerHeight / window.innerWidth;
    uniforms.u_mouse.value.x = (e.pageX - window.innerWidth / 2) / window.innerWidth / ratio;
    uniforms.u_mouse.value.y = (e.pageY - window.innerHeight / 2) / window.innerHeight * -1;
    
    e.preventDefault();
  });
}

function onWindowResize( event ) {
  renderer.setSize( window.innerWidth, window.innerHeight );
  uniforms.u_resolution.value.x = renderer.domElement.width;
  uniforms.u_resolution.value.y = renderer.domElement.height;
}

function animate(delta) {
  requestAnimationFrame( animate );
  render(delta);
}






let capturer = new CCapture( { 
  verbose: true, 
  framerate: 60,
  // motionBlurFrames: 4,
  quality: 90,
  format: 'webm',
  workersPath: 'js/'
 } );
let capturing = false;

isCapturing = function(val) {
  if(val === false && window.capturing === true) {
    capturer.stop();
    capturer.save();
  } else if(val === true && window.capturing === false) {
    capturer.start();
  }
  capturing = val;
}
toggleCapture = function() {
  isCapturing(!capturing);
}

window.addEventListener('keyup', function(e) { if(e.keyCode == 68) toggleCapture(); });

let then = 0;
function render(delta) {
  
  uniforms.u_time.value = -10000 + delta * 0.0005;
  renderer.render( scene, camera );
  
  if(capturing) {
    capturer.capture( renderer.domElement );
  }
}
</script>