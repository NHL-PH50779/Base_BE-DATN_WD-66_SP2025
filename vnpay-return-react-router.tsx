// Nếu dùng React Router, thêm route này vào App.js hoặc router config
import { Route } from 'react-router-dom';
import VNPayReturn from './components/VNPayReturn';

// Trong component App hoặc Routes
<Route path="/vnpay-return" component={VNPayReturn} />

// Hoặc với React Router v6:
<Route path="/vnpay-return" element={<VNPayReturn />} />