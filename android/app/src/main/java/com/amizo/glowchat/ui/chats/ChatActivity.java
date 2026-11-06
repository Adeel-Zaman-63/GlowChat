package com.amizo.glowchat.ui.chats;

import android.os.Bundle;
import android.view.View;
import android.widget.EditText;
import android.widget.ImageButton;
import android.widget.TextView;

import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.lifecycle.ViewModelProvider;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.amizo.glowchat.R;
import com.amizo.glowchat.data.local.entity.ConversationEntity;

public class ChatActivity extends AppCompatActivity {

    public static final String EXTRA_CONVERSATION_ID =
            "com.amizo.glowchat.EXTRA_CONVERSATION_ID";

    private ConversationViewModel conversationViewModel;

    private TextView chatTitle;
    private RecyclerView messageRecyclerView;
    private TextView emptyMessage;

    private MessageAdapter messageAdapter;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        String conversationId =
                getIntent().getStringExtra(EXTRA_CONVERSATION_ID);

        if (conversationId == null || conversationId.trim().isEmpty()) {
            finish();
            return;
        }

        WindowCompat.enableEdgeToEdge(getWindow());
        setContentView(R.layout.activity_chat);

        View root = findViewById(R.id.chat_root);

        ViewCompat.setOnApplyWindowInsetsListener(root, (view, insets) -> {
            Insets systemBars = insets.getInsets(
                    WindowInsetsCompat.Type.systemBars()
            );

            view.setPadding(
                    systemBars.left,
                    systemBars.top,
                    systemBars.right,
                    systemBars.bottom
            );

            return insets;
        });

        chatTitle = findViewById(R.id.text_chat_title);
        messageRecyclerView = findViewById(R.id.recycler_chat_messages);
        emptyMessage = findViewById(R.id.text_chat_empty);

        ImageButton backButton = findViewById(R.id.button_chat_back);
        EditText messageInput = findViewById(R.id.input_chat_message);
        ImageButton sendButton = findViewById(R.id.button_send_message);

        backButton.setOnClickListener(view -> finish());

        // Sending remains disabled until message encryption is integrated.
        messageInput.setEnabled(false);
        sendButton.setEnabled(false);

        messageAdapter = new MessageAdapter();

        messageRecyclerView.setLayoutManager(
                new LinearLayoutManager(this)
        );

        messageRecyclerView.setAdapter(messageAdapter);

        messageAdapter.submitList(null);
        messageRecyclerView.setVisibility(View.GONE);
        emptyMessage.setVisibility(View.VISIBLE);

        conversationViewModel = new ViewModelProvider(this)
                .get(ConversationViewModel.class);

        conversationViewModel.observeConversation(conversationId)
                .observe(this, this::displayConversation);
    }

    private void displayConversation(ConversationEntity conversation) {

        if (conversation == null) {
            finish();
            return;
        }

        String title = conversation.title;

        if (title == null || title.trim().isEmpty()) {
            title = getString(R.string.unknown_conversation);
        }

        chatTitle.setText(title);
    }
}
