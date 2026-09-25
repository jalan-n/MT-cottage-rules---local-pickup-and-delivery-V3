import { defineConfig } from 'vite';
import path from 'path';

export default defineConfig(() => {
  return {
    root: '.',
    publicDir: 'public',
    server: {
      port: 3000,
      host: '0.0.0.0',
      hmr: process.env.DISABLE_HMR !== 'true',
      watch: process.env.DISABLE_HMR === 'true' ? null : {},
      // Proxy dynamic PHP micro-API and admin dashboard calls to local PHP dev server on port 8088
      proxy: {
        '/api': {
          target: 'http://127.0.0.1:8088',
          changeOrigin: true
        },
        '/admin': {
          target: 'http://127.0.0.1:8088',
          changeOrigin: true
        }
      }
    },
    build: {
      outDir: 'dist',
      emptyOutDir: true,
      rollupOptions: {
        input: {
          main: path.resolve(__dirname, 'index.html'),
          shop: path.resolve(__dirname, 'public/shop.html'),
          packs: path.resolve(__dirname, 'public/packs.html'),
          giftBox: path.resolve(__dirname, 'public/gift-box.html'),
          customOrders: path.resolve(__dirname, 'public/custom-orders.html'),
          contact: path.resolve(__dirname, 'public/contact.html'),
          cart: path.resolve(__dirname, 'public/cart.html'),
          checkout: path.resolve(__dirname, 'public/checkout.html')
        }
      }
    }
  };
});
