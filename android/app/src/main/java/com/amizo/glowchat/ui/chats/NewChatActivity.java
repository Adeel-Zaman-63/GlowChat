package com.amizo.glowchat.ui.chats;

import android.os.Bundle;
import android.widget.ImageButton;

import androidx.appcompat.app.AppCompatActivity;

import com.amizo.glowchat.R;

public class NewChatActivity extends AppCompatActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        setContentView(R.layout.activity_new_chat);

        ImageButton backButton = findViewById(
                R.id.button_new_chat_back
        );

        backButton.setOnClickListener(view -> finish());
    }
}
