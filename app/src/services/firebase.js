import { initializeApp } from 'firebase/app';
import { getDatabase } from 'firebase/database';

const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY || 'AIzaSyAguJa2Qwpki89nzQMvIHUE8QZug1trxcs',
  databaseURL:
    import.meta.env.VITE_FIREBASE_DATABASE_URL ||
    'https://highway-123e3-default-rtdb.asia-southeast1.firebasedatabase.app'
};

const app = initializeApp(firebaseConfig);
const db = getDatabase(app);

export { app, db };
