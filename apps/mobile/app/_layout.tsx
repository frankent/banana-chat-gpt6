import {Stack} from 'expo-router';
import {StatusBar} from 'expo-status-bar';
export default function Layout(){return <><StatusBar style="dark"/><Stack screenOptions={{headerStyle:{backgroundColor:'#f8f9f5'},headerTintColor:'#354131',headerShadowVisible:false,contentStyle:{backgroundColor:'#fff'}}}/></>}
