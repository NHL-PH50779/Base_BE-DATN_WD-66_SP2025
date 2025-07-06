// Trong file App.js hoặc router config của bạn
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import VNPayReturn from './components/VNPayReturn'; // đường dẫn đến component

function App() {
  return (
    <Router>
      <Routes>
        {/* Các route khác */}
        <Route path="/checkout" element={<Checkout />} />
        <Route path="/orders" element={<Orders />} />
        
        {/* Route xử lý VNPay return */}
        <Route path="/vnpay-return" element={<VNPayReturn />} />
      </Routes>
    </Router>
  );
}

export default App;