import { useEffect, useState } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';

const VNPayReturn = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const [status, setStatus] = useState('processing');

  useEffect(() => {
    const handleVNPayReturn = async () => {
      try {
        // Gọi API xác nhận thanh toán
        const queryString = location.search;
        const response = await fetch(`http://127.0.0.1:8000/api/payment/vnpay-return${queryString}`);
        const result = await response.json();

        if (result.success) {
          setStatus('success');
          setTimeout(() => {
            navigate('/orders');
          }, 3000);
        } else {
          setStatus('failed');
          setTimeout(() => {
            navigate('/checkout');
          }, 3000);
        }
      } catch (error) {
        console.error('Error processing VNPay return:', error);
        setStatus('failed');
        setTimeout(() => {
          navigate('/checkout');
        }, 3000);
      }
    };

    if (location.search) {
      handleVNPayReturn();
    }
  }, [location.search, navigate]);

  if (status === 'processing') {
    return (
      <div className="min-h-screen flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4">Đang xử lý kết quả thanh toán...</p>
        </div>
      </div>
    );
  }

  if (status === 'success') {
    return (
      <div className="min-h-screen flex items-center justify-center bg-green-50">
        <div className="text-center p-8 bg-white rounded-lg shadow-lg">
          <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <h1 className="text-2xl font-bold text-green-600 mb-2">Thanh toán thành công!</h1>
          <p className="text-gray-600 mb-4">Đơn hàng của bạn đã được thanh toán thành công.</p>
          <p className="text-sm text-gray-500">Đang chuyển hướng...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-red-50">
      <div className="text-center p-8 bg-white rounded-lg shadow-lg">
        <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </div>
        <h1 className="text-2xl font-bold text-red-600 mb-2">Thanh toán thất bại!</h1>
        <p className="text-gray-600 mb-4">Có lỗi xảy ra trong quá trình thanh toán.</p>
        <p className="text-sm text-gray-500">Đang chuyển về trang thanh toán...</p>
      </div>
    </div>
  );
};

export default VNPayReturn;