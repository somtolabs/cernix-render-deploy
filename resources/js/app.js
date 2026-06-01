import './bootstrap';
import jsQR from 'jsqr';

window.jsQR = jsQR;
window.dispatchEvent(new Event('cernix:scanner-ready'));
