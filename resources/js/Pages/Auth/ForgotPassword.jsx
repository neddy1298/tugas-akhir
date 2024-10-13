import React from 'react';
import { Mail } from 'lucide-react';
import { useForm } from '@inertiajs/react';
import AuthLayout from '@/Layouts/AuthLayout';

const ForgotPassword = () => {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('password.email'), {
            onFinish: () => reset('email'),
        });
    };

    return (
        <AuthLayout title="Forgot Password">
            <form onSubmit={handleSubmit}>
                <div className="mb-4">
                    <label htmlFor="email" className="block mb-1 text-sm font-medium text-gray-700">Email</label>
                    <div className="relative">
                        <input
                            type="email"
                            id="email"
                            className="w-full px-3 py-2 pl-10 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="NIM@unida.ac.id"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            required
                        />
                        <Mail className="absolute text-gray-400 transform -translate-y-1/2 left-3 top-1/2" size={18} />
                        {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
                    </div>
                </div>
                <button
                    type="submit"
                    className="w-full px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Send Reset Link
                </button>
            </form>
        </AuthLayout>
    );
};

export default ForgotPassword;