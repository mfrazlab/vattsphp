import React from 'react';
import Dashboard from "./components/Dashboard";
import {AuthGuard} from "@vatts/auth/react";

export default function Welcome() {

  return (
    <AuthGuard redirectTo={"/auth"}>
      <Dashboard></Dashboard>
    </AuthGuard>
  );
}
